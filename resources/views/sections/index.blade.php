<x-app-shell>
    <div class="app-page-header" style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:32px;">
        <div>
            <h1 style="font-family:'DM Sans',sans-serif; font-size:32px; font-weight:400; margin-bottom:8px;">Sections & Tasks</h1>
            <p style="color:hsl(24,5%,45%); font-size:14px;">Manage kitchen sections and the daily prep tasks in each. Any chef can complete any task.</p>
        </div>
        <button onclick="openAddSection()"
                style="background:hsl(20,60%,45%); color:white; padding:10px 18px; border-radius:6px; font-size:14px; font-weight:500; border:none; cursor:pointer;">
            + Add Section
        </button>
    </div>

    @if($sections->isEmpty())
        <div style="text-align:center; padding:64px; color:hsl(24,5%,45%); background:white; border:1px solid hsl(30,15%,90%); border-radius:8px;">
            No sections yet. Add one to get started.
        </div>
    @else
        <div style="display:flex; flex-direction:column; gap:20px;">
            @foreach($sections as $section)
                <div style="background:white; border:1px solid hsl(30,15%,90%); border-radius:8px; overflow:hidden;">

                    {{-- Section header --}}
                    <div style="padding:18px 20px; border-bottom:1px solid hsl(30,15%,92%); display:flex; justify-content:space-between; align-items:flex-start;">
                        <div>
                            <div style="font-size:17px; font-weight:600; margin-bottom:4px;">{{ $section->name }}</div>
                            @if($section->description)
                                <div style="font-size:13px; color:hsl(24,5%,50%);">{{ $section->description }}</div>
                            @endif
                            <div style="margin-top:8px; font-size:13px; display:flex; align-items:center; gap:8px; flex-wrap:wrap;">
                                <span style="color:hsl(24,5%,55%); font-size:12px;">
                                    {{ empty($section->active_days) ? 'Every day' : collect($section->active_days)->map(fn ($d) => ucfirst(substr($d, 0, 3)))->implode(', ') }}
                                </span>
                            </div>
                        </div>
                        <div style="display:flex; gap:8px; align-items:center; flex-shrink:0; margin-left:16px;">
                            <button onclick="openEditSection({{ $section->id }}, '{{ addslashes($section->name) }}', '{{ addslashes($section->description ?? '') }}', '{{ implode(',', $section->active_days ?? []) }}')"
                                    style="font-size:13px; color:hsl(20,60%,45%); background:none; border:none; cursor:pointer; font-weight:500;">
                                Edit
                            </button>
                            <span style="color:hsl(30,15%,80%);">|</span>
                            <form action="{{ route('sections.destroy', $section) }}" method="POST"
                                  onsubmit="return confirm('Delete section {{ addslashes($section->name) }} and all its tasks?')" style="display:inline;">
                                @csrf
                                @method('DELETE')
                                <button type="submit"
                                        style="background:none; border:none; cursor:pointer; color:hsl(0,70%,50%); padding:4px;" title="Delete section">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/></svg>
                                </button>
                            </form>
                        </div>
                    </div>

                    {{-- Tasks --}}
                    <div style="padding:16px 20px;">
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px;">
                            <span style="font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:0.06em; color:hsl(24,5%,50%);">
                                Tasks ({{ $section->tasks->count() }})
                            </span>
                            <button onclick="openAddTask({{ $section->id }}, '{{ addslashes($section->name) }}')"
                                    style="font-size:12px; color:hsl(20,60%,45%); background:none; border:none; cursor:pointer; font-weight:600;">
                                + Add Task
                            </button>
                        </div>

                        @if($section->tasks->isEmpty())
                            <p style="font-size:13px; color:hsl(30,15%,65%); padding:8px 0;">No tasks yet.</p>
                        @else
                            <div style="display:flex; flex-direction:column; gap:8px;">
                                @foreach($section->tasks as $task)
                                    <div style="display:flex; justify-content:space-between; align-items:center; padding:10px 14px; background:hsl(30,15%,98%); border:1px solid hsl(30,15%,92%); border-radius:6px;">
                                        <div>
                                            <div style="display:flex; align-items:center; gap:8px;">
                                                <span style="font-size:14px; font-weight:500;">{{ $task->title }}</span>
                                                @if(!$task->requires_photo)
                                                    <span style="font-size:11px; background:hsl(200,60%,93%); color:hsl(200,60%,35%); padding:1px 7px; border-radius:4px; font-weight:600;">No photo</span>
                                                @endif
                                            </div>
                                            @if($task->description)
                                                <div style="font-size:12px; color:hsl(24,5%,50%); margin-top:2px;">{{ $task->description }}</div>
                                            @endif
                                        </div>
                                        <div style="display:flex; gap:8px; align-items:center; flex-shrink:0; margin-left:12px;">
                                            <button onclick="openEditTask({{ $task->id }}, {{ $section->id }}, '{{ addslashes($task->title) }}', '{{ addslashes($task->description ?? '') }}', {{ $task->requires_photo ? 'true' : 'false' }})"
                                                    style="font-size:12px; color:hsl(20,60%,45%); background:none; border:none; cursor:pointer; font-weight:500;">
                                                Edit
                                            </button>
                                            <span style="color:hsl(30,15%,80%);">|</span>
                                            <form action="{{ route('sections.tasks.destroy', [$section, $task]) }}" method="POST"
                                                  onsubmit="return confirm('Delete task: {{ addslashes($task->title) }}?')" style="display:inline;">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit"
                                                        style="background:none; border:none; cursor:pointer; color:hsl(0,70%,50%); padding:2px;" title="Delete task">
                                                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/></svg>
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    {{-- Add Section Modal --}}
    <div id="add-section-modal"
         style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.5); z-index:50; align-items:center; justify-content:center; padding:16px;">
        <div style="background:white; border-radius:8px; padding:24px; width:100%; max-width:420px;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
                <h2 style="font-family:'DM Sans',sans-serif; font-size:20px; font-weight:400;">Add Section</h2>
                <button onclick="closeModal('add-section-modal')"
                        style="background:none; border:none; cursor:pointer; font-size:20px; color:hsl(24,5%,45%);">×</button>
            </div>
            <form action="{{ route('sections.store') }}" method="POST">
                @csrf
                <div style="margin-bottom:16px;">
                    <label style="display:block; font-size:14px; font-weight:500; margin-bottom:4px;">Section Name</label>
                    <input type="text" name="name" required maxlength="100" placeholder="e.g. Hot Kitchen"
                           style="width:100%; padding:8px 12px; border:1px solid hsl(30,15%,85%); border-radius:6px; font-size:14px; box-sizing:border-box;">
                </div>
                <div style="margin-bottom:16px;">
                    <label style="display:block; font-size:14px; font-weight:500; margin-bottom:4px;">Description <span style="font-weight:400; color:hsl(24,5%,55%);">(optional)</span></label>
                    <input type="text" name="description" maxlength="255" placeholder="Brief description"
                           style="width:100%; padding:8px 12px; border:1px solid hsl(30,15%,85%); border-radius:6px; font-size:14px; box-sizing:border-box;">
                </div>
                <div style="margin-bottom:20px;">
                    <label style="display:block; font-size:14px; font-weight:500; margin-bottom:8px;">Active Days</label>
                    @include('partials.day-picker', ['prefix' => 'add'])
                </div>
                <div style="display:flex; justify-content:flex-end; gap:8px;">
                    <button type="button" onclick="closeModal('add-section-modal')"
                            style="padding:8px 16px; font-size:14px; background:none; border:none; cursor:pointer;">Cancel</button>
                    <button type="submit"
                            style="background:hsl(20,60%,45%); color:white; padding:8px 16px; border-radius:6px; font-size:14px; font-weight:500; border:none; cursor:pointer;">
                        Create
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- Edit Section Modal --}}
    <div id="edit-section-modal"
         style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.5); z-index:50; align-items:center; justify-content:center; padding:16px;">
        <div style="background:white; border-radius:8px; padding:24px; width:100%; max-width:420px;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
                <h2 style="font-family:'DM Sans',sans-serif; font-size:20px; font-weight:400;">Edit Section</h2>
                <button onclick="closeModal('edit-section-modal')"
                        style="background:none; border:none; cursor:pointer; font-size:20px; color:hsl(24,5%,45%);">×</button>
            </div>
            <form id="edit-section-form" method="POST">
                @csrf
                @method('PATCH')
                <div style="margin-bottom:16px;">
                    <label style="display:block; font-size:14px; font-weight:500; margin-bottom:4px;">Section Name</label>
                    <input type="text" name="name" id="edit-section-name" required maxlength="100"
                           style="width:100%; padding:8px 12px; border:1px solid hsl(30,15%,85%); border-radius:6px; font-size:14px; box-sizing:border-box;">
                </div>
                <div style="margin-bottom:16px;">
                    <label style="display:block; font-size:14px; font-weight:500; margin-bottom:4px;">Description <span style="font-weight:400; color:hsl(24,5%,55%);">(optional)</span></label>
                    <input type="text" name="description" id="edit-section-desc" maxlength="255"
                           style="width:100%; padding:8px 12px; border:1px solid hsl(30,15%,85%); border-radius:6px; font-size:14px; box-sizing:border-box;">
                </div>
                <div style="margin-bottom:20px;">
                    <label style="display:block; font-size:14px; font-weight:500; margin-bottom:8px;">Active Days</label>
                    @include('partials.day-picker', ['prefix' => 'edit'])
                </div>
                <div style="display:flex; justify-content:flex-end; gap:8px;">
                    <button type="button" onclick="closeModal('edit-section-modal')"
                            style="padding:8px 16px; font-size:14px; background:none; border:none; cursor:pointer;">Cancel</button>
                    <button type="submit"
                            style="background:hsl(20,60%,45%); color:white; padding:8px 16px; border-radius:6px; font-size:14px; font-weight:500; border:none; cursor:pointer;">
                        Save
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- Add Task Modal --}}
    <div id="add-task-modal"
         style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.5); z-index:50; align-items:center; justify-content:center; padding:16px;">
        <div style="background:white; border-radius:8px; padding:24px; width:100%; max-width:420px;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:6px;">
                <h2 style="font-family:'DM Sans',sans-serif; font-size:20px; font-weight:400;">Add Task</h2>
                <button onclick="closeModal('add-task-modal')"
                        style="background:none; border:none; cursor:pointer; font-size:20px; color:hsl(24,5%,45%);">×</button>
            </div>
            <p id="add-task-section-label" style="font-size:13px; color:hsl(24,5%,50%); margin-bottom:18px;"></p>
            <form id="add-task-form" method="POST">
                @csrf
                <div style="margin-bottom:16px;">
                    <label style="display:block; font-size:14px; font-weight:500; margin-bottom:4px;">Task Title</label>
                    <input type="text" name="title" required maxlength="150" placeholder="e.g. Clean the grill"
                           style="width:100%; padding:8px 12px; border:1px solid hsl(30,15%,85%); border-radius:6px; font-size:14px; box-sizing:border-box;">
                </div>
                <div style="margin-bottom:16px;">
                    <label style="display:block; font-size:14px; font-weight:500; margin-bottom:4px;">Description <span style="font-weight:400; color:hsl(24,5%,55%);">(optional)</span></label>
                    <input type="text" name="description" maxlength="255" placeholder="Short instruction"
                           style="width:100%; padding:8px 12px; border:1px solid hsl(30,15%,85%); border-radius:6px; font-size:14px; box-sizing:border-box;">
                </div>
                <div style="margin-bottom:20px;">
                    <label style="display:flex; align-items:center; gap:10px; cursor:pointer;">
                        {{-- An unticked checkbox posts nothing, and the controller reads
                             absence as "yes". Without this hidden field unticking is
                             silently discarded — which is why every task on production
                             required a photo. Same pairing as is_open_order on sales;
                             the hidden field must come FIRST so a ticked box overrides it. --}}
                        <input type="hidden" name="requires_photo" value="0">
                        <input type="checkbox" name="requires_photo" value="1" checked
                               style="width:16px; height:16px; accent-color:hsl(20,60%,45%); cursor:pointer;">
                        <span style="font-size:14px; font-weight:500;">Requires photo proof</span>
                    </label>
                    <p style="font-size:12px; color:hsl(24,5%,55%); margin-top:4px; margin-left:26px;">Uncheck if this task doesn't need a photo uploaded.</p>
                </div>
                <div style="display:flex; justify-content:flex-end; gap:8px;">
                    <button type="button" onclick="closeModal('add-task-modal')"
                            style="padding:8px 16px; font-size:14px; background:none; border:none; cursor:pointer;">Cancel</button>
                    <button type="submit"
                            style="background:hsl(20,60%,45%); color:white; padding:8px 16px; border-radius:6px; font-size:14px; font-weight:500; border:none; cursor:pointer;">
                        Add Task
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- Edit Task Modal --}}
    <div id="edit-task-modal"
         style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.5); z-index:50; align-items:center; justify-content:center; padding:16px;">
        <div style="background:white; border-radius:8px; padding:24px; width:100%; max-width:420px;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
                <h2 style="font-family:'DM Sans',sans-serif; font-size:20px; font-weight:400;">Edit Task</h2>
                <button onclick="closeModal('edit-task-modal')"
                        style="background:none; border:none; cursor:pointer; font-size:20px; color:hsl(24,5%,45%);">×</button>
            </div>
            <form id="edit-task-form" method="POST">
                @csrf
                @method('PATCH')
                <div style="margin-bottom:16px;">
                    <label style="display:block; font-size:14px; font-weight:500; margin-bottom:4px;">Task Title</label>
                    <input type="text" name="title" id="edit-task-title" required maxlength="150"
                           style="width:100%; padding:8px 12px; border:1px solid hsl(30,15%,85%); border-radius:6px; font-size:14px; box-sizing:border-box;">
                </div>
                <div style="margin-bottom:16px;">
                    <label style="display:block; font-size:14px; font-weight:500; margin-bottom:4px;">Description <span style="font-weight:400; color:hsl(24,5%,55%);">(optional)</span></label>
                    <input type="text" name="description" id="edit-task-desc" maxlength="255"
                           style="width:100%; padding:8px 12px; border:1px solid hsl(30,15%,85%); border-radius:6px; font-size:14px; box-sizing:border-box;">
                </div>
                <div style="margin-bottom:20px;">
                    <label style="display:flex; align-items:center; gap:10px; cursor:pointer;">
                        <input type="hidden" name="requires_photo" value="0">
                        <input type="checkbox" name="requires_photo" id="edit-task-requires-photo" value="1"
                               style="width:16px; height:16px; accent-color:hsl(20,60%,45%); cursor:pointer;">
                        <span style="font-size:14px; font-weight:500;">Requires photo proof</span>
                    </label>
                    <p style="font-size:12px; color:hsl(24,5%,55%); margin-top:4px; margin-left:26px;">Uncheck if this task doesn't need a photo uploaded.</p>
                </div>
                <div style="display:flex; justify-content:flex-end; gap:8px;">
                    <button type="button" onclick="closeModal('edit-task-modal')"
                            style="padding:8px 16px; font-size:14px; background:none; border:none; cursor:pointer;">Cancel</button>
                    <button type="submit"
                            style="background:hsl(20,60%,45%); color:white; padding:8px 16px; border-radius:6px; font-size:14px; font-weight:500; border:none; cursor:pointer;">
                        Save
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function closeModal(id) {
            document.getElementById(id).style.display = 'none';
        }

        function openAddSection() {
            document.getElementById('add-section-modal').style.display = 'flex';
        }

        function openEditSection(id, name, desc, activeDaysCsv) {
            document.getElementById('edit-section-name').value = name;
            document.getElementById('edit-section-desc').value = desc;
            setDayPills('edit', activeDaysCsv ? activeDaysCsv.split(',') : []);
            document.getElementById('edit-section-form').action = '/sections/' + id;
            document.getElementById('edit-section-modal').style.display = 'flex';
        }

        const ALL_DAYS = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];

        function toggleDayPill(checkbox) {
            const label = checkbox.closest('label');
            label.style.background = checkbox.checked ? 'hsl(20,60%,45%)' : 'white';
            label.style.color = checkbox.checked ? 'white' : 'hsl(24,10%,15%)';
            label.style.borderColor = checkbox.checked ? 'hsl(20,60%,45%)' : 'hsl(30,15%,85%)';
        }

        function setDayPills(prefix, activeDays) {
            ALL_DAYS.forEach((day) => {
                const checkbox = document.getElementById(prefix + '-day-' + day);
                checkbox.checked = activeDays.includes(day);
                toggleDayPill(checkbox);
            });
        }

        function openAddTask(sectionId, sectionName) {
            document.getElementById('add-task-section-label').textContent = 'Section: ' + sectionName;
            document.getElementById('add-task-form').action = '/sections/' + sectionId + '/tasks';
            document.getElementById('add-task-form').reset();
            document.getElementById('add-task-modal').style.display = 'flex';
        }

        function openEditTask(taskId, sectionId, title, desc, requiresPhoto) {
            document.getElementById('edit-task-title').value = title;
            document.getElementById('edit-task-desc').value = desc;
            document.getElementById('edit-task-requires-photo').checked = requiresPhoto;
            document.getElementById('edit-task-form').action = '/sections/' + sectionId + '/tasks/' + taskId;
            document.getElementById('edit-task-modal').style.display = 'flex';
        }
    </script>
</x-app-shell>
