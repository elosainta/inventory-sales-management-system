<x-app-shell>
    <div class="app-page-header" style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:32px;">
        <div>
            <h1 style="font-family:'DM Sans',sans-serif; font-size:32px; font-weight:400; margin-bottom:8px;">Suppliers</h1>
            <p style="color:hsl(24,5%,45%); font-size:14px;">The people who keep your kitchen stocked.</p>
        </div>
        @can('manage-suppliers')
        <button
            onclick="document.getElementById('add-modal').style.display='flex'"
            style="background-color:hsl(20,60%,45%); color:white; padding:8px 16px; border-radius:6px; font-size:14px; font-weight:500; border:none; cursor:pointer;">
            + Add Supplier
        </button>
        @endcan
    </div>

    @if($suppliers->isEmpty())
        <div style="text-align:center; padding:64px; color:hsl(24,5%,45%);">
            No suppliers yet. Add your first one.
        </div>
    @else
        <div style="display:grid; grid-template-columns:repeat(auto-fit,minmax(260px,1fr)); gap:16px;">
            @foreach($suppliers as $supplier)
                <div style="background:white; border:1px solid hsl(30,15%,90%); border-radius:8px; padding:20px;">
                    <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:12px;">
                        <h3 style="font-family:'DM Sans',sans-serif; font-size:17px; font-weight:500;">{{ $supplier->name }}</h3>
                        <div style="display:flex; gap:4px; align-items:center;">
                            @can('manage-suppliers')
                            <button onclick="openEditModal(this)"
                                    data-edit-url="{{ route('suppliers.update', $supplier) }}"
                                    data-name="{{ $supplier->name }}"
                                    data-contact="{{ $supplier->contact }}"
                                    data-email="{{ $supplier->email }}"
                                    data-address="{{ $supplier->address }}"
                                    style="background:none; border:none; cursor:pointer; color:hsl(24,5%,45%); padding:4px;" title="Edit">
                                <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                            </button>
                            @endcan
                            @can('delete-entries')
                            <form action="{{ route('suppliers.destroy', $supplier) }}" method="POST"
                                  onsubmit="return confirm('Remove this supplier?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit"
                                        style="background:none; border:none; cursor:pointer; color:hsl(0,70%,50%); padding:4px;" title="Remove">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/></svg>
                                </button>
                            </form>
                            @endcan
                        </div>
                    </div>
                    <div style="font-size:14px; color:hsl(24,5%,45%); line-height:1.8;">
                        <div>{{ $supplier->contact }}</div>
                        <div>{{ $supplier->email }}</div>
                        <div>{{ $supplier->address }}</div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    @can('manage-suppliers')
    {{-- Edit Supplier Modal --}}
    <div id="edit-modal"
         onclick="if(event.target===this)document.getElementById('edit-modal').style.display='none'"
         style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.5); z-index:50; align-items:center; justify-content:center; padding:16px;">
        <div style="background:white; border-radius:8px; padding:24px; width:100%; max-width:448px;">
            <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:16px;">
                <div>
                    <h2 style="font-family:'DM Sans',sans-serif; font-size:22px; font-weight:400;">Edit Supplier</h2>
                    <p style="font-size:13px; color:hsl(24,5%,45%);">Update this partner's details.</p>
                </div>
                <button onclick="document.getElementById('edit-modal').style.display='none'"
                        style="background:none; border:none; cursor:pointer; font-size:20px; color:hsl(24,5%,45%);">×</button>
            </div>

            <form id="edit-form" method="POST">
                @csrf
                @method('PATCH')
                <div style="margin-bottom:16px;">
                    <label style="display:block; font-size:14px; font-weight:500; margin-bottom:4px;">Name</label>
                    <input type="text" id="edit-name" name="name" required
                           style="width:100%; padding:8px 12px; border:1px solid hsl(30,15%,85%); border-radius:6px; font-size:14px; box-sizing:border-box;">
                </div>
                <div style="margin-bottom:16px;">
                    <label style="display:block; font-size:14px; font-weight:500; margin-bottom:4px;">Phone</label>
                    <input type="text" id="edit-contact" name="contact" required
                           style="width:100%; padding:8px 12px; border:1px solid hsl(30,15%,85%); border-radius:6px; font-size:14px; box-sizing:border-box;">
                </div>
                <div style="margin-bottom:16px;">
                    <label style="display:block; font-size:14px; font-weight:500; margin-bottom:4px;">Email</label>
                    <input type="email" id="edit-email" name="email" required
                           style="width:100%; padding:8px 12px; border:1px solid hsl(30,15%,85%); border-radius:6px; font-size:14px; box-sizing:border-box;">
                </div>
                <div style="margin-bottom:16px;">
                    <label style="display:block; font-size:14px; font-weight:500; margin-bottom:4px;">Address</label>
                    <input type="text" id="edit-address" name="address" required
                           style="width:100%; padding:8px 12px; border:1px solid hsl(30,15%,85%); border-radius:6px; font-size:14px; box-sizing:border-box;">
                </div>
                <div style="display:flex; justify-content:flex-end; gap:8px; padding-top:8px;">
                    <button type="button"
                            onclick="document.getElementById('edit-modal').style.display='none'"
                            style="padding:8px 16px; font-size:14px; background:none; border:none; cursor:pointer;">
                        Cancel
                    </button>
                    <button type="submit"
                            style="background-color:hsl(20,60%,45%); color:white; padding:8px 16px; border-radius:6px; font-size:14px; font-weight:500; border:none; cursor:pointer;">
                        Save changes
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openEditModal(btn) {
            var d = btn.dataset;
            document.getElementById('edit-form').action = d.editUrl;
            document.getElementById('edit-name').value = d.name;
            document.getElementById('edit-contact').value = d.contact;
            document.getElementById('edit-email').value = d.email;
            document.getElementById('edit-address').value = d.address;
            document.getElementById('edit-modal').style.display = 'flex';
            document.getElementById('edit-name').focus();
        }
    </script>

    {{-- Add Supplier Modal --}}
    <div id="add-modal"
         style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.5); z-index:50; align-items:center; justify-content:center; padding:16px;">
        <div style="background:white; border-radius:8px; padding:24px; width:100%; max-width:448px;">
            <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:16px;">
                <div>
                    <h2 style="font-family:'DM Sans',sans-serif; font-size:22px; font-weight:400;">New Supplier</h2>
                    <p style="font-size:13px; color:hsl(24,5%,45%);">Add a partner to your kitchen.</p>
                </div>
                <button onclick="document.getElementById('add-modal').style.display='none'"
                        style="background:none; border:none; cursor:pointer; font-size:20px; color:hsl(24,5%,45%);">×</button>
            </div>

            <form action="{{ route('suppliers.store') }}" method="POST">
                @csrf
                <div style="margin-bottom:16px;">
                    <label style="display:block; font-size:14px; font-weight:500; margin-bottom:4px;">Name</label>
                    <input type="text" name="name" required
                           style="width:100%; padding:8px 12px; border:1px solid hsl(30,15%,85%); border-radius:6px; font-size:14px; box-sizing:border-box;">
                </div>
                <div style="margin-bottom:16px;">
                    <label style="display:block; font-size:14px; font-weight:500; margin-bottom:4px;">Phone</label>
                    <input type="text" name="contact" required
                           style="width:100%; padding:8px 12px; border:1px solid hsl(30,15%,85%); border-radius:6px; font-size:14px; box-sizing:border-box;">
                </div>
                <div style="margin-bottom:16px;">
                    <label style="display:block; font-size:14px; font-weight:500; margin-bottom:4px;">Email</label>
                    <input type="email" name="email" required
                           style="width:100%; padding:8px 12px; border:1px solid hsl(30,15%,85%); border-radius:6px; font-size:14px; box-sizing:border-box;">
                </div>
                <div style="margin-bottom:16px;">
                    <label style="display:block; font-size:14px; font-weight:500; margin-bottom:4px;">Address</label>
                    <input type="text" name="address" required
                           style="width:100%; padding:8px 12px; border:1px solid hsl(30,15%,85%); border-radius:6px; font-size:14px; box-sizing:border-box;">
                </div>
                <div style="display:flex; justify-content:flex-end; gap:8px; padding-top:8px;">
                    <button type="button"
                            onclick="document.getElementById('add-modal').style.display='none'"
                            style="padding:8px 16px; font-size:14px; background:none; border:none; cursor:pointer;">
                        Cancel
                    </button>
                    <button type="submit"
                            style="background-color:hsl(20,60%,45%); color:white; padding:8px 16px; border-radius:6px; font-size:14px; font-weight:500; border:none; cursor:pointer;">
                        Add Supplier
                    </button>
                </div>
            </form>
        </div>
    </div>
    @endcan
</x-app-shell>