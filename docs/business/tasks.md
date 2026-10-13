# Tasks, projects and the matrix

- **T1** A task has a title (required, ≤ 200 chars, whitespace collapsed), optional notes, an optional project, an optional **planned day** (`plannedOn`: the day I intend to do it) and an optional **deadline** (`dueOn`). — `Task`
- **T2** Planning shortcuts: Today, Tomorrow, Next week (next Monday), a picked date, or not planned. Resolved on the server in the **user's time zone**. — `PlanShortcut`, `PlanTaskHandler`
- **T3** **Today** is not a project: it lists open tasks from every project (and the inbox) planned on or before today, or due on or before today, plus tasks completed today. Tasks planned on an earlier day and not due yet appear under **From earlier**; "Move all to today" plans them for today. — `ListTodayTasksHandler`, `MoveOverdueToTodayHandler`
- **T4** A project page shows all its open tasks whatever their planned day, plus tasks done in the last 14 days. Adding a task to Today from there only sets its planned day; it stays in its project. — `ListProjectTasksHandler`
- **T5** **Upcoming** shows open tasks planned over 7 days, starting today (or a chosen day). — `ListUpcomingTasksHandler`
- **T6** **Inbox** = open tasks without a project. — `ListInboxTasksHandler`
- **T7** Deleting a project keeps its tasks, which move to the inbox. — `Task.project` `onDelete: SET NULL`
- **T8** Project names are unique per user (case-insensitive), ≤ 60 chars, with one of 8 colours (berry, honey, moss, fjord, heather, blossom, lichen, rust). — `CreateProjectHandler`, `EditProjectHandler`, `ProjectColor`
- **T13** **Bulk selection**: in every list (Today, Upcoming, Inbox, projects, matrix), an open task is selected with Ctrl/⌘- or Shift-click on its title, `x` on the focused row, or Select in its menu (phones); once something is selected, a plain click selects too. The bar at the bottom then completes (subtasks before their parents, each earning its reward, G1), plans (today, tomorrow, next week, not planned), sorts into a quadrant, moves to a project or the inbox (subtasks stay with their parent, S5) or deletes the selection, one task at a time through the usual rules; refused tasks are counted and the first reason shown. Escape, the ✕ or changing page clears the selection. While something is selected, dragging tasks (onto a parent, between quadrants or days) is off, so a click never starts a drag. — `useSelection`, `BulkBar.vue`, `tasks/bulk.js`

## Subtasks

Made to split a piece of work (a drawing) into achievable steps (sketch, colouring, render).

- **S1** A task can be split into subtasks, added from its menu ("Add a subtask", shortcut `s`, then the + below its subtasks) or from its editor. A subtask has the same owner and project as its parent and is otherwise a full task (planned day, deadline, quadrant, notes); a new one starts on its parent's planned day, so it shows up next to it. One level only: a subtask cannot have subtasks. — `Task::addSubtask()`, `AddSubtaskHandler`
- **S2** Subtasks are listed in the order they were added. In every list, a subtask whose parent is in the same list is shown under it; otherwise it shows "Subtask of …". A parent shows its progress (done / total). — `ListSubtasksHandler`, `assets/vue/tasks/subtasks.js`
- **S3** Completing the last open subtask completes the parent. Each subtask earns its own reward, and so does the parent when it completes (G1): the completion reward is their sum. A parent with an open subtask cannot be completed by hand. — `Task::complete()`, `CompleteTaskHandler`
- **S4** Reopening a subtask reopens its parent; adding a subtask to a done parent reopens it. Neither earns anything again (G1). A reopened parent without open subtasks can be completed by hand. — `Task::reopen()`, `Task::addSubtask()`
- **S5** A subtask stays in its parent's project: moving the parent moves its subtasks, a subtask cannot be moved on its own. Deleting the parent deletes its subtasks; deleting a subtask keeps the parent. — `Task::fileUnder()`, `Task.parent` `onDelete: CASCADE`
- **S6** A subtask cannot repeat. The next occurrence of a repeating parent (R2) starts with open copies of its subtasks (title and notes). — `Task::repeat()`, `Task::nextOccurrence()`
- **S7** Dragging a task onto another one in a list makes it a subtask of that task: it takes the parent's project and keeps its own days; an open one reopens a done parent. It cannot be dropped on itself, on a subtask, nor be a task with subtasks or a repeating task. "Make it a task" in a subtask's menu takes it out of its parent, in the same project. Its old parent is not completed by either move. — `Task::nestUnder()`, `Task::promote()`, `ChangeParentHandler`, `canNest()`

## Eisenhower matrix

- **M1** Quadrants: Water = urgent & important (`DoFirst`), Plant = important, not urgent (`Schedule`), Trim = urgent, not important (`Delegate`), Compost = neither (`Eliminate`). A task may be unsorted.
- **M2** Inside a quadrant, tasks have a rank set by drag and drop; a task newly put in a quadrant goes last. Done tasks cannot be reordered. — `ReorderQuadrantHandler`, `ClassifyTaskHandler`
- **M3** **Ordering rule of every list**: open before done → Water, Plant, Trim, Compost, unsorted → rank → deadline (none last) → creation. — `DoctrineTaskQueries::ordered()`, mirrored by `assets/vue/tasks/compareTasks.js`
- **M4** The matrix can be filtered by project (several at once, **Inbox** standing for tasks without a project); the filter lives in the URL (`?projects=`). Reordering a filtered quadrant moves only the visible tasks, hidden ones keep their place. Each quadrant keeps the same size and scrolls inside. — `assets/vue/tasks/projectFilter.js`, `MatrixPage.vue`

## Recurrence

- **R1** An open task can repeat every *n* days, weeks, months or years (1 ≤ *n* ≤ 365), set in the task editor; a done task cannot start repeating. — `Recurrence`, `RecurrenceUnit`, `Task::repeat()`, `EditTaskHandler`
- **R2** Completing a repeating task creates its next occurrence: same owner, title, notes, project and quadrant (ranked last in it), open, with the same recurrence. Each occurrence is its own task and earns its own reward once (G1). — `Task::nextOccurrence()`, `ContinueSeries`, `CompleteTaskHandler`
- **R3** Dates of the next occurrence: the anchor is the planned day, else the deadline, else today (user's time zone); it moves forward by the recurrence until it is strictly after today (an overdue series skips the missed steps). The planned day becomes the moved anchor when the task had one (or had no date at all); a deadline moves by the same number of days, keeping its gap with the planned day. Months and years keep the day of the month, clamped to the last day of a shorter month (Jan 31 + 1 month = Feb 28/29). — `Recurrence::next()`, `RecurrenceTest`, `TaskTest`, `RecurringTasksTest`
- **R4** The series travels with the open occurrence: the completed task loses its recurrence, so reopening and completing it again creates nothing new, and deleting the open occurrence (or setting it to never repeat) stops the series. — `Task::nextOccurrence()`, `RecurringTasksTest`

## Import from Super Productivity

- **T9** Settings › Import from Super Productivity takes a Super Productivity backup (`.json`, with or without its `data` wrapper). Only its open tasks come over: done and archived tasks stay behind and earn nothing. The SPA sends only the `task`, `project` and `boards` parts. A file without tasks is refused (`task.unreadable_backup`). — `SuperProductivityBackup`, `ImportSuperProductivityHandler`, `ImportSuperProductivityController`
- **T10** Projects come over by name: a project with the same name (case-insensitive, cut to 60 chars) is reused, otherwise it is created with the next colour of the palette. Tasks of Super Productivity's Inbox go to the inbox, and a subtask follows its parent (S5). Repeat configurations are not imported: a repeating task comes over as a plain task.
- **T11** The `EM_URGENT` and `EM_IMPORTANT` tags give the quadrant (`Quadrant::of`), ranked after the tasks already there in the order of Super Productivity's Eisenhower board; a task with neither tag stays unsorted, never Compost. The planned day is `dueDay`, else the day of `dueWithTime` in the user's time zone, else today for a task tagged Today; the deadline is `deadlineDay` or the day of `deadlineWithTime`; notes and creation date are kept.
- **T12** Each imported task remembers its Super Productivity id (`Task.externalId` = `sp:<id>`, unique per user): importing the same backup again skips the tasks already imported, even if they were edited, completed or moved since. The answer counts imported tasks, created projects and skipped tasks. — `TaskRepository::linked()`
