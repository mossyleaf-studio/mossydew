# Friends and shared goals

Every user still owns their own tasks (A3); friends only see each other's progress.

## Friends

- **F1** **Invite a friend** creates a link `/friends/join/<token>` (48 hex chars; only its SHA-256 is stored). A link works **once**, for **7 days**. — `FriendInvite`, `CreateInviteHandler`
- **F2** Opening the link shows who invites (signing in first if needed, then back to the link); **Accept** makes both users friends. Refused: one's own link (`friend.own_invite`), a used one (`friend.invite_used`), an expired one (`friend.invite_expired`), or two users already friends (`friend.already_friends`). An unknown link is 404. — `AcceptInviteHandler`, `FriendInvite::check()`
- **F3** A friendship is one row per pair, whoever invited (`friendship_pair`, ids ordered). — `Friendship::between()`
- **F4** A friend shows their name, level, XP, current streak (in their own time zone), best streak and number of species collected; never their tasks, projects, greenhouse or account. — `FriendCard`, `ListFriendsHandler`
- **F5** Either friend can remove the other; nothing else is deleted, and a new link can bring them back. A user who is not your friend answers 404. — `RemoveFriendHandler`, `FriendshipRepository::with()`
