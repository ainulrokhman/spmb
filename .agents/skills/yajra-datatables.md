---
description: Implement server-side Yajra DataTables for resource listing in Laravel 12
glob: "**/*.{php,js,blade.php}"
---

# Yajra DataTables Integration Skill

Use this guide/procedure when implementing server-side Yajra DataTables processing and pagination in the Laravel application.

## Implementation Guide

1. **Blade Structure**:
   - Define an empty HTML table with a unique `#id` (e.g., `<table id="resource-table">`).
   - Specify the headers (`<thead>`) but leave the table body (`<tbody>`) empty.

2. **JavaScript Setup**:
   - Initialize the DataTable pointing to the data source URL via AJAX.
   - Example:
     ```javascript
     $('#resource-table').DataTable({
         processing: true,
         serverSide: true,
         ajax: "{{ route('resource.index') }}",
         columns: [
             { data: 'name', name: 'name' },
             { data: 'actions', name: 'actions', orderable: false, searchable: false }
         ]
     });
     ```

3. **Controller Setup**:
   - Detect if the request is an AJAX request using `$request->ajax()`.
   - Construct the query, selecting only the necessary columns (always select the primary key `id` as it is required under the hood to build URLs and relations, but omit it from the UI columns). Use eager loading (`with`) to avoid N+1 query performance issues.
   - Wrap the query in `datatables()->of($query)`.
   - Add custom columns (such as buttons) with `addColumn` and list raw HTML columns using `rawColumns`.
   - Finalize using `->make(true)` to return the JSON response.
   - Example:
     ```php
     if ($request->ajax()) {
         $query = ModelName::select(['id', 'name', 'relationship_id'])->with('relationship');
         return datatables()->of($query)
             ->addColumn('actions', function ($row) {
                 return view('resource.partials.actions', compact('row'))->render();
             })
             ->rawColumns(['actions'])
             ->make(true);
     }
     ```
