<?php

namespace App\DataTables;

use App\Models\User;
use App\Support\SystemAccess;
use Illuminate\Http\JsonResponse;
use Yajra\DataTables\Facades\DataTables;

class UserDataTable
{
    /**
     * Build the DataTable response.
     *
     * @return JsonResponse
     */
    public function make()
    {
        $query = User::with('roles')->select(['id', 'name', 'email', 'created_at']);

        return DataTables::of($query)
            ->addColumn('user_details', $this->userDetailsColumn())
            ->addColumn('assigned_roles', $this->assignedRolesColumn())
            ->addColumn('joined_date', $this->joinedDateColumn())
            ->addColumn('actions', $this->actionsColumn())
            ->filterColumn('user_details', function ($query, $keyword) {
                $query->where(function ($q) use ($keyword) {
                    $q->where('name', 'like', "%{$keyword}%")
                        ->orWhere('email', 'like', "%{$keyword}%");
                });
            })
            ->orderColumn('user_details', function ($query, $order) {
                $query->orderBy('name', $order);
            })
            ->rawColumns(['user_details', 'assigned_roles', 'joined_date', 'actions'])
            ->make(true);
    }

    /**
     * Build the `user_details` column renderer.
     */
    private function userDetailsColumn(): \Closure
    {
        return function ($user) {
            $initials = collect(explode(' ', $user->name))->map(fn ($n) => substr($n, 0, 1))->take(2)->join('');
            $colors = ['bg-primary', 'bg-success', 'bg-info', 'bg-warning', 'bg-danger', 'bg-secondary'];
            $bgColor = $colors[$user->id % count($colors)];
            $avatar = '<div class="'.$bgColor.' text-white rounded-circle d-flex align-items-center justify-content-center fw-bold me-3 shadow-sm" style="width: 44px; height: 44px; font-size: 0.95rem;">'.strtoupper($initials).'</div>';

            return '<div class="d-flex align-items-center">'.$avatar.'<div><span class="fw-bold text-body d-block">'.e($user->name).'</span><span class="text-muted small">'.e($user->email).'</span></div></div>';
        };
    }

    /**
     * Build the `assigned_roles` column renderer.
     */
    private function assignedRolesColumn(): \Closure
    {
        return function ($user) {
            $html = '<div class="d-flex flex-wrap gap-1">';
            foreach ($user->roles as $role) {
                $badgeClass = 'bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-10';
                if ($role->name === SystemAccess::SUPER_ADMIN_ROLE) {
                    $badgeClass = 'bg-danger bg-opacity-10 text-danger border border-danger border-opacity-20';
                } elseif ($role->name === SystemAccess::ADMIN_ROLE) {
                    $badgeClass = 'bg-primary bg-opacity-10 text-primary border border-primary border-opacity-20';
                } elseif ($role->name === SystemAccess::USER_ROLE) {
                    $badgeClass = 'bg-success bg-opacity-10 text-success border border-success border-opacity-20';
                }
                $html .= '<span class="badge '.$badgeClass.' rounded-pill py-1.5 px-2.5 small fw-semibold">'.e($role->name).'</span>';
            }
            if ($user->roles->isEmpty()) {
                $html .= '<span class="text-muted small italic">No roles assigned</span>';
            }
            $html .= '</div>';

            return $html;
        };
    }

    /**
     * Build the `joined_date` column renderer.
     */
    private function joinedDateColumn(): \Closure
    {
        return function ($user) {
            return '<span class="text-secondary small">'.$user->created_at->format('M d, Y').'</span>';
        };
    }

    /**
     * Build the `actions` column renderer.
     */
    private function actionsColumn(): \Closure
    {
        return function ($user) {
            $html = '<div class="d-flex justify-content-end gap-2">';
            if (auth()->user()->can('user-edit')) {
                $html .= '<a href="'.route('admin.users.edit', $user->id).'" class="btn btn-sm btn-light border py-1.5 px-2.5 rounded-2" title="Edit User"><i class="bi bi-pencil-square text-primary"></i> Edit</a>';
            }
            if (auth()->user()->can('user-delete')) {
                if (auth()->id() === $user->id) {
                    $html .= '<button class="btn btn-sm btn-light border py-1.5 px-2.5 rounded-2" disabled title="You cannot delete yourself"><i class="bi bi-trash-fill text-muted"></i></button>';
                } elseif ($user->email === SystemAccess::SUPER_ADMIN_EMAIL) {
                    $html .= '<button class="btn btn-sm btn-light border py-1.5 px-2.5 rounded-2" disabled title="Cannot delete Super Admin account"><i class="bi bi-trash-fill text-muted"></i></button>';
                } else {
                    $html .= '<form action="'.route('admin.users.destroy', $user->id).'" method="POST" class="d-inline" onsubmit="return confirm(\'Are you sure you want to delete user '.e($user->name).'?\')">';
                    $html .= csrf_field().method_field('DELETE');
                    $html .= '<button type="submit" class="btn btn-sm btn-light border py-1.5 px-2.5 rounded-2" title="Delete User"><i class="bi bi-trash text-danger"></i></button>';
                    $html .= '</form>';
                }
            }
            $html .= '</div>';

            return $html;
        };
    }
}
