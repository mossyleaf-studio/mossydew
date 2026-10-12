# Business rules

| UI term | Code | Page |
|---|---|---|
| Task | `Domain\Planning\Task` | [tasks.md](tasks.md) |
| Project | `Domain\Planning\Project` | [tasks.md](tasks.md) |
| Subtask | `Domain\Planning\Task` (`parent`) | [tasks.md](tasks.md) |
| Today, Upcoming, Inbox | `Application\Planning\List*TasksHandler` | [tasks.md](tasks.md) |
| Matrix: Water / Plant / Trim / Compost | `Domain\Planning\Quadrant` (`DoFirst`, `Schedule`, `Delegate`, `Eliminate`) | [tasks.md](tasks.md) |
| XP, level, streak | `Domain\Gamification\{Player, LevelCurve, RewardPolicy, Streak}` | [gamification.md](gamification.md) |
| Herbarium, species, specimen | `Domain\Gamification\Herbarium\{SpeciesCatalog, SpeciesDraw, Specimen}` | [gamification.md](gamification.md) |
| Greenhouse, pot, dew, facility, expedition | `Domain\Gamification\Greenhouse\{Greenhouse, Pot, Facility, DewPolicy}` | [greenhouse.md](greenhouse.md) |
| Achievements | `Domain\Gamification\Achievement\*` | [gamification.md](gamification.md) |
| Done, statistics | `Application\Planning\{ListDoneTasks, ShowStatistics}\*` | [history.md](history.md) |
| Account, sign-in, theme | `Domain\Identity\*` | [accounts.md](accounts.md) |
| Import from Super Productivity | `Application\Planning\ImportSuperProductivity\*` | [tasks.md](tasks.md) |
| Injust Release link, token, journal | `Domain\Planning\Injust\*`, `Domain\Identity\AccessToken` | [integrations.md](integrations.md) |
| Friends, invitation link | `Domain\Social\{FriendInvite, Friendship}` | [social.md](social.md) |
