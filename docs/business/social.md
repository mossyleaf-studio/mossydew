# Friends and shared goals

Every user still owns their own tasks (A3); friends only see each other's progress.

## Friends

- **F1** **Invite a friend** creates a link `/friends/join/<token>` (48 hex chars; only its SHA-256 is stored). A link works **once**, for **7 days**. — `FriendInvite`, `CreateInviteHandler`
- **F2** Opening the link shows who invites (signing in first if needed, then back to the link); **Accept** makes both users friends. Refused: one's own link (`friend.own_invite`), a used one (`friend.invite_used`), an expired one (`friend.invite_expired`), or two users already friends (`friend.already_friends`). An unknown link is 404. — `AcceptInviteHandler`, `FriendInvite::check()`
- **F3** A friendship is one row per pair, whoever invited (`friendship_pair`, ids ordered). — `Friendship::between()`
- **F4** A friend shows their name, level, XP, current streak (in their own time zone), best streak and number of species collected; never their tasks, projects, greenhouse or account. — `FriendCard`, `ListFriendsHandler`
- **F5** Either friend can remove the other; nothing else is deleted, and a new link can bring them back. A user who is not your friend answers 404. — `RemoveFriendHandler`, `FriendshipRepository::with()`

## Shared goals

- **SG1** A shared goal is a title (≤ 80 chars), a number of tasks to complete together (1 to 1000) and an end day (from the creator's today on). Its creator picks at least one friend (F5: only friends, others 404); everyone picked is a member at once. — `SharedGoal::set()`, `SetGoalHandler`
- **SG2** Members see every goal they are in, with each member's contribution, on the Friends page; a goal is reachable only by its members (others 404). A member can leave; a goal with nobody left is deleted. Leaving never removes what one already contributed to the total. — `SharedGoalRepository`, `LeaveGoalHandler`, `GoalView`
- **SG3** Every task that earns its reward (G1: once per task, subtasks and parents included) adds 1 to every **active** goal of its owner: not reached yet and today (in the owner's time zone) on or before the end day. Reopening never subtracts. — `AdvanceGoals`, `SharedGoal::contribute()`, `CompleteTaskHandler`
- **SG4** When the total reaches the target, the goal is **reached** (once, `reachedAt`) and **every member** gets one moss drawn like a level-up (G7) and dew equal to the target. The completion that reached it shows "Shared goal reached" to its author; the others find the moss in their herbarium. — `AdvanceGoals`
- **SG5** Goals are locked before greenhouses, in id order, so two members completing at the same time never deadlock or count twice. — `DoctrineSharedGoalRepository::lockedOpenOf()`
- **SG6** Reaching a first shared goal unlocks the achievement **Common ground** (checked on the member's next completion, G9). — `Rule\CommonGround`, `PlayerStats::goalsReached`
