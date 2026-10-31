# Thornbridge: notes for contributors and coding agents

A text adventure whose one NPC, Grimble the troll, is voiced by Claude: Laravel 13 (PHP 8.4), the Anthropic PHP SDK, Vue 3 with
Tailwind 4, SQLite. The model only proposes; the server referees. `README.md` explains the design. Read it before touching
`app/Game` or `app/Ai`.

## Commands

```sh
composer run dev                            # app server, queue listener and Vite
php artisan test                            # Pest suite, no network (phpunit.xml forces AI_DRIVER=scripted)
vendor/bin/pint                             # format PHP (CI runs vendor/bin/pint --test)
vendor/bin/phpstan analyse                  # Larastan, level 7
npm run typecheck                           # vue-tsc
php artisan thornbridge:eval --driver=scripted   # cheat/honest scripts; non-zero exit on a wrong outcome
```

## Invariants to keep

- **The model never changes state directly.** `NpcDecision` is a proposal; only `RuleBook::apply()` turns it into state.
  Every new rule lives in `RuleBook` as a pure function with a rule id and a `Ruling`, and gets a test in `tests/Unit/RuleBookTest.php`.
- **Permission checks read the state from before the decision** (`$before` in `RuleBook::apply()`), so trust granted this
  turn can't unlock haggling or passage this turn. Passage grounds are computed only by `RuleBook::passageGrounds()`.
- **The model's levers are a closed set.** Adding an action means a case in `NpcAction`, a check and a `perform()` branch in
  `RuleBook`, and the enum flows into `NpcDecision::schema()` (strict, `additionalProperties: false`). Keep the schema
  and `NpcDecision::fromArray()` in step.
- **Facts come from deterministic verbs, not the model.** Gold moves only through `pay`, items only through `give`/`take`,
  and the riddle is judged by `World::RIDDLE_ANSWER_PATTERN` in `GameEngine::say()`. Never put the answer in `NpcPrompt`
  or `ClaudeNpcBrain::SYSTEM`.
- **`NpcPrompt` is the model's whole view.** It renders the NPC-visible state lines, a transcript rebuilt from `game_events`
  by `GameEngine::transcript()` (never from client input), and player text inside `<traveller_says>` escaped with
  `htmlspecialchars` so it can't close the tag. Keep `ClaudeNpcBrain::SYSTEM` static so prompt caching keeps working.
- **A turn commits once.** `GameEngine::runTurn()` collects events in `$pending` and writes state and events in a single
  transaction at the end, so a thrown model call leaves the game untouched. Don't write to the DB mid-turn.
- **One turn per game at a time.** `GameEngine::play()` holds `Cache::lock("game-turn:{id}")`; a second turn gets
  `GameBusyException` (409). `GameEngine` keeps per-turn state in properties, so don't bind it as a singleton.
- **The repair loop is bounded.** A rejected action is re-asked `REPAIR_ATTEMPTS` (1) times with the rejection reason; the
  first attempt's rulings are stored with `superseded: true`, which the Rule Ledger strikes through.
- **Refusals degrade, they don't error.** `ClaudeNpcBrain` sends `fallbacks: 'default'` with the fallback beta header; a
  remaining refusal throws `NpcRefusedException`, which `GameEngine::decide()` turns into a silent troll.
- **Access is a signed capability.** Game routes sit behind `EnsureGameToken` (HMAC of the id under `APP_KEY`, compared
  with `hash_equals`); a bad token is a 404, same as an unknown game. Token-spending routes keep their `throttle:npc` /
  `throttle:new-game` limiters, and `World::MAX_NPC_TURNS` caps each game.

## Conventions

- Tests never hit the network. Feature tests swap in `Tests\Support\QueuedNpcBrain` via `app()->instance(NpcBrain::class, …)`
  to script decisions, throwables or closures and inspect the prompts sent; `ClaudeNpcBrainTest` drives the real SDK through
  a fake PSR-18 transport and asserts on the request body.
- `ScriptedNpcBrain` is deliberately gullible (it obeys `SYSTEM:` orders) so the RuleBook's rejections are visible offline.
  Don't "fix" that. `AppServiceProvider` also binds it when `ANTHROPIC_API_KEY` is empty, so a fresh clone stays playable.
