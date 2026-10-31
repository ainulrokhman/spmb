@extends('layouts.admin')

@section('title', 'Activity Logs')
@section('page-title', 'Audit Activity Logs')

@section('breadcrumb')
    <li class="breadcrumb-item active" aria-current="page">Activity Logs</li>
@endsection

@section('content')
<div class="card border-0 shadow-sm">
    <div class="card-header bg-transparent border-0 d-flex flex-column flex-md-row align-items-md-center justify-content-between pt-4 px-4 gap-3">
        <div>
            <h5 class="mb-0 fw-bold text-body">System Activities</h5>
            <p class="text-muted small mb-0">Track all model modifications, creations, deletions, and actor details</p>
        </div>
        
        <div class="d-flex align-items-center gap-3">
            <!-- Search Form -->
            <form id="search-form" class="position-relative" onsubmit="return false;">
                <input type="text" 
                       name="search" 
                       value="" 
                       class="form-control bg-light bg-opacity-75 border-secondary border-opacity-20 py-2 ps-5 rounded-pill" 
                       placeholder="Search activities..." 
                       style="width: 250px;">
                <i class="bi bi-search position-absolute text-muted" style="left: 18px; top: 50%; transform: translateY(-50%);"></i>
                <a href="#" id="clear-search" class="position-absolute text-muted hover-text-dark d-none" style="right: 18px; top: 50%; transform: translateY(-50%);">
                    <i class="bi bi-x-circle-fill"></i>
                </a>
            </form>
        </div>
    </div>

    <div class="card-body px-4 pb-4">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" id="activity-table" style="width:100%">
                <thead class="table-light">
                    <tr>
                        <th style="width: 80px;" class="text-secondary fw-semibold">ID</th>
                        <th class="text-secondary fw-semibold">Actor</th>
                        <th class="text-secondary fw-semibold">Action</th>
                        <th class="text-secondary fw-semibold">Subject</th>
                        <th class="text-secondary fw-semibold">Occurred At</th>
                        <th style="width: 120px;" class="text-secondary fw-semibold text-end">Details</th>
                    </tr>
                </thead>
                <tbody>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Details Modal -->
<div class="modal fade" id="detailsModal" tabindex="-1" aria-labelledby="detailsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header border-bottom px-4">
                <h5 class="modal-title fw-bold text-body" id="detailsModalLabel">Activity Log Details</h5>
                <button type="button" class="btn-close" data-bs-redirect="modal" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <div class="row g-3 mb-4">
                    <div class="col-6 col-sm-3">
                        <span class="text-muted d-block small text-uppercase fw-semibold">Actor</span>
                        <span id="modal-actor" class="fw-medium text-body">N/A</span>
                    </div>
                    <div class="col-6 col-sm-3">
                        <span class="text-muted d-block small text-uppercase fw-semibold">Action</span>
                        <span id="modal-action" class="fw-medium text-body">N/A</span>
                    </div>
                    <div class="col-6 col-sm-3">
                        <span class="text-muted d-block small text-uppercase fw-semibold">Subject Type</span>
                        <span id="modal-subject" class="fw-medium text-body">N/A</span>
                    </div>
                    <div class="col-6 col-sm-3">
                        <span class="text-muted d-block small text-uppercase fw-semibold">Timestamp</span>
                        <span id="modal-time" class="fw-medium text-body">N/A</span>
                    </div>
                </div>

                <h6 class="fw-bold text-body mb-3">Changes & Parameters</h6>
                <div id="modal-content-area">
                    <!-- Dynamic details tables go here -->
                </div>
            </div>
            <div class="modal-footer border-top px-4">
                <button type="button" class="btn btn-secondary py-2 px-4 fw-medium" style="border-radius: 8px;" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    window.addEventListener('DOMContentLoaded', () => {
        const table = $('#activity-table').DataTable({
            processing: true,
            serverSide: true,
            ajax: {
                url: "{{ route('admin.activity-logs.index') }}",
                type: 'GET'
            },
            columns: [
                { data: 'id', name: 'id' },
                { data: 'actor', name: 'actor' },
                { data: 'action', name: 'action' },
                { data: 'subject', name: 'subject' },
                { data: 'created_at_formatted', name: 'created_at' },
                { data: 'actions', name: 'actions', orderable: false, searchable: false, class: 'text-end' }
            ],
            order: [[0, 'desc']],
            dom: '<"d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-3 mb-3"l>rt<"d-flex flex-column flex-md-row justify-content-between align-items-center gap-3 mt-4 pt-3 border-top"ip>',
            language: {
                processing: '<div class="spinner-border text-primary spinner-border-sm" role="status"><span class="visually-hidden">Loading...</span></div> Loading logs...',
                lengthMenu: "Show _MENU_ entries"
            },
            drawCallback: function() {
                $('.dataTables_paginate .paginate_button').addClass('page-item');
                $('.dataTables_paginate .paginate_button a').addClass('page-link');
            }
        });

        // Link external search bar
        const $searchInput = $('input[name="search"]');
        const $clearBtn = $('#clear-search');

        $searchInput.on('input', function() {
            if (this.value) {
                $clearBtn.removeClass('d-none');
            } else {
                $clearBtn.addClass('d-none');
            }
            table.search(this.value).draw();
        });

        $clearBtn.on('click', function(e) {
            e.preventDefault();
            $searchInput.val('');
            $clearBtn.addClass('d-none');
            table.search('').draw();
        });

        // Modal Action Trigger
        $('#activity-table').on('click', '.view-details-btn', function() {
            const btn = $(this);
            const props = btn.data('properties');
            const id = btn.data('id');
            const actor = btn.data('causer');
            const event = btn.data('event');
            const subject = btn.data('subject');
            const time = btn.data('time');

            // Populate Modal metadata
            $('#modal-actor').text(actor);
            $('#modal-action').html(btn.closest('tr').find('td:eq(2)').html()); // borrow the color badge
            $('#modal-subject').text(subject);
            $('#modal-time').text(time);

            let contentHtml = '';

            // Check if properties has 'attributes' and 'old' objects (standard Spatie updated event format)
            if (props && (props.attributes || props.old)) {
                contentHtml += '<div class="table-responsive"><table class="table table-bordered table-striped align-middle small mb-0">';
                contentHtml += '<thead class="table-light"><tr><th>Attribute</th>';
                if (props.old) contentHtml += '<th>Old Value</th>';
                if (props.attributes) contentHtml += '<th>New Value</th>';
                contentHtml += '</tr></thead><tbody>';

                // Collect all unique keys from both attributes and old
                const allKeys = new Set([
                    ...Object.keys(props.attributes || {}),
                    ...Object.keys(props.old || {})
                ]);

                allKeys.forEach(key => {
                    // Hide sensitive keys or internal timestamps if desired
                    if (key === 'password' || key === 'remember_token') {
                        contentHtml += `<tr><td class="fw-bold">${key}</td>`;
                        if (props.old) contentHtml += `<td><span class="text-muted">[Hidden / Encrypted]</span></td>`;
                        if (props.attributes) contentHtml += `<td><span class="text-muted">[Hidden / Encrypted]</span></td>`;
                        contentHtml += `</tr>`;
                    } else {
                        const oldVal = props.old && props.old[key] !== undefined ? JSON.stringify(props.old[key]) : '-';
                        const newVal = props.attributes && props.attributes[key] !== undefined ? JSON.stringify(props.attributes[key]) : '-';
                        
                        // Highlight change if they differ
                        const isChanged = oldVal !== newVal;
                        const rowClass = isChanged ? 'table-warning table-opacity-10' : '';

                        contentHtml += `<tr class="${rowClass}"><td class="fw-bold">${key}</td>`;
                        if (props.old) contentHtml += `<td class="text-secondary">${escapeHtml(oldVal)}</td>`;
                        if (props.attributes) contentHtml += `<td class="text-body fw-medium">${escapeHtml(newVal)}</td>`;
                        contentHtml += `</tr>`;
                    }
                });

                contentHtml += '</tbody></table></div>';
            } else if (props && Object.keys(props).length > 0) {
                // If it is just general custom attributes properties
                contentHtml += `<pre class="bg-light p-3 rounded text-body border" style="max-height: 300px; overflow-y: auto;"><code>${escapeHtml(JSON.stringify(props, null, 2))}</code></pre>`;
            } else {
                contentHtml += '<div class="text-center text-muted py-4"><i class="bi bi-info-circle me-1"></i> No attributes parameters changed.</div>';
            }

            $('#modal-content-area').html(contentHtml);
            
            // Show modal
            const myModal = new bootstrap.Modal(document.getElementById('detailsModal'));
            myModal.show();
        });

        // Simple helper to escape html
        function escapeHtml(str) {
            if (typeof str !== 'string') return str;
            return str
                .replace(/&/g, "&amp;")
                .replace(/</g, "&lt;")
                .replace(/>/g, "&gt;")
                .replace(/"/g, "&quot;")
                .replace(/'/g, "&#039;");
        }
    });
</script>
@endpush
