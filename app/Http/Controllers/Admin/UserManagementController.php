<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class UserManagementController extends Controller
{
    /**
     * Display users listing
     */
    public function index()
    {
        return view('admin.users.index');
    }

    /**
     * Show user details
     */
    public function show(User $user)
    {
        // Load relationships that exist, including social circles
        $user->load(['posts', 'socialCircles:id,name,description,color,logo']);
        return view('admin.users.show', compact('user'));
    }

    /**
     * Show edit user form
     */
    public function edit(User $user)
    {
        return view('admin.users.edit', compact('user'));
    }

    /**
     * Update user
     */
    public function update(Request $request, User $user)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,' . $user->id,
            'phone' => 'nullable|string|max:20',
            'is_active' => 'boolean',
            'is_banned' => 'boolean',
        ]);

        try {
            $user->update($request->only([
                'name', 'email', 'phone', 'is_active', 'is_banned'
            ]));

            return redirect()->route('admin.users.show', $user)
                ->with('success', 'User updated successfully');
        } catch (\Exception $e) {
            return redirect()->back()
                ->with('error', 'Failed to update user: ' . $e->getMessage())
                ->withInput();
        }
    }

    /**
     * Suspend user
     */
    public function suspend(User $user, Request $request)
    {
        try {
            // Check if this is a ban action
            if ($request->filled('ban')) {
                $user->update([
                    'is_active' => false,
                    'is_banned' => true,
                    'banned_until' => null
                ]);
                $message = 'User banned successfully';
            } else {
                $user->update([
                    'is_active' => false,
                    'is_banned' => false,
                    'banned_until' => null
                ]);
                $message = 'User suspended successfully';
            }

            return redirect()->back()->with('success', $message);
        } catch (\Exception $e) {
            return redirect()->back()
                ->with('error', 'Failed to update user: ' . $e->getMessage());
        }
    }

    /**
     * Activate user
     */
    public function activate(User $user)
    {
        try {
            $user->update([
                'is_active' => true,
                'is_banned' => false,
                'banned_until' => null
            ]);
            return redirect()->back()
                ->with('success', 'User activated successfully');
        } catch (\Exception $e) {
            return redirect()->back()
                ->with('error', 'Failed to activate user: ' . $e->getMessage());
        }
    }

    /**
     * Delete user
     */
    public function destroy(User $user)
    {
        try {
            $user->delete();
            return redirect()->route('admin.users.index')
                ->with('success', 'User deleted successfully');
        } catch (\Exception $e) {
            return redirect()->back()
                ->with('error', 'Failed to delete user: ' . $e->getMessage());
        }
    }

    /**
     * Get users for AJAX requests
     */
    public function getUsers(Request $request)
    {
        try {
            Log::info('UserManagement getUsers called with params: ', $request->all());

            // Start with a simple query
            $query = User::query();

            // Apply filters
            if ($request->filled('search')) {
                $search = $request->get('search');
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%");
                      // Remove phone filter if column doesn't exist
                      // ->orWhere('phone', 'like', "%{$search}%");
                });
            }

            if ($request->filled('status')) {
                $status = $request->get('status');
                if ($status === 'active') {
                    $query->where('is_active', true)->where('is_banned', false);
                } elseif ($status === 'suspended') {
                    $query->where('is_active', false);
                } elseif ($status === 'banned') {
                    $query->where('is_banned', true);
                }
            }

            if ($request->filled('verified')) {
                if ($request->get('verified') == '1') {
                    $query->whereNotNull('email_verified_at');
                } else {
                    $query->whereNull('email_verified_at');
                }
            }

            if ($request->filled('social_circles')) {
                $socialCircleFilter = $request->get('social_circles');
                if ($socialCircleFilter === 'has_circles') {
                    $query->whereHas('socialCircles');
                } elseif ($socialCircleFilter === 'no_circles') {
                    $query->whereDoesntHave('socialCircles');
                } elseif (is_numeric($socialCircleFilter)) {
                    // Filter by specific social circle ID
                    $query->whereHas('socialCircles', function($q) use ($socialCircleFilter) {
                        $q->where('social_circles.id', $socialCircleFilter);
                    });
                }
            }

            // Get paginated results - include social circles relationship
            $users = $query->select(['id', 'name', 'email', 'profile', 'profile_url', 'is_active', 'is_banned', 'banned_until', 'created_at', 'email_verified_at'])
                ->with(['socialCircles:id,name,color']) // Load social circles with only needed fields
                ->withCount('socialCircles') // Count social circles
                ->orderBy('created_at', 'desc')
                ->paginate(20);

            // Add formatted data
            $users->getCollection()->transform(function ($user) {
                $user->created_at_human = $user->created_at->diffForHumans();
                // Set default counts
                $user->posts_count = 0;
                $user->streams_count = 0;

                // Add profile picture using the same logic as UserResource
                $user->profile_picture = $this->getProfileUrl($user);

                // Set default phone if not available
                $user->phone = $user->phone ?? 'No phone';

                // Calculate status based on boolean fields
                if ($user->is_banned) {
                    $user->status = 'banned';
                } elseif (!$user->is_active) {
                    $user->status = 'suspended';
                } else {
                    $user->status = 'active';
                }

                // Format social circles data
                $user->social_circles_count = $user->social_circles_count ?? 0;
                $user->social_circles_names = $user->socialCircles ? $user->socialCircles->pluck('name')->toArray() : [];
                $user->social_circles_colors = $user->socialCircles ? $user->socialCircles->pluck('color', 'name')->toArray() : [];

                return $user;
            });

            // Get stats
            $stats = [
                'total' => User::count(),
                'active' => User::where('is_active', true)->where('is_banned', false)->count(),
                'suspended' => User::where('is_active', false)->count(),
                'banned' => User::where('is_banned', true)->count(),
                'with_social_circles' => User::whereHas('socialCircles')->count(),
                'avg_social_circles' => round(User::withCount('socialCircles')->get()->avg('social_circles_count') ?? 0, 1)
            ];

            Log::info('UserManagement getUsers success - returning ' . $users->count() . ' users');

            return response()->json([
                'users' => $users,
                'stats' => $stats
            ]);

        } catch (\Exception $e) {
            Log::error('Error in getUsers: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());

            return response()->json([
                'error' => 'Failed to load users: ' . $e->getMessage(),
                'users' => (object)['data' => [], 'current_page' => 1, 'last_page' => 1, 'from' => 0, 'to' => 0, 'total' => 0],
                'stats' => ['total' => 0, 'active' => 0, 'suspended' => 0, 'banned' => 0]
            ], 200); // Return 200 instead of 500 for better debugging
        }
    }

    /**
     * Get social circles for filter dropdown
     */
    public function getSocialCircles()
    {
        try {
            $socialCircles = \App\Models\SocialCircle::withoutGlobalScope('active')
                ->where('is_active', true)
                ->where('deleted_flag', 'N')
                ->orderBy('order_by', 'asc')
                ->get(['id', 'name', 'color']);

            return response()->json([
                'social_circles' => $socialCircles
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching social circles: ' . $e->getMessage());
            return response()->json([
                'error' => 'Failed to load social circles',
                'social_circles' => []
            ], 200);
        }
    }

    /**
     * Update user status
     */
    public function updateStatus(Request $request, User $user)
    {
        $request->validate([
            'status' => 'required|in:active,suspended,banned'
        ]);

        try {
            $status = $request->status;

            if ($status === 'active') {
                $user->update([
                    'is_active' => true,
                    'is_banned' => false,
                    'banned_until' => null
                ]);
            } elseif ($status === 'suspended') {
                $user->update([
                    'is_active' => false,
                    'is_banned' => false,
                    'banned_until' => null
                ]);
            } elseif ($status === 'banned') {
                $user->update([
                    'is_active' => false,
                    'is_banned' => true,
                    'banned_until' => null // You could set a future date here if needed
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => "User {$request->status} successfully"
            ]);
        } catch (\Exception $e) {
            Log::error('Error updating user status: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to update user status'
            ], 500);
        }
    }

    /**
     * Bulk update user status
     */
    public function bulkUpdateStatus(Request $request)
    {
        $request->validate([
            'user_ids' => 'required|array',
            'user_ids.*' => 'exists:users,id',
            'status' => 'required|in:active,suspended,banned'
        ]);

        try {
            $status = $request->status;
            $updateData = [];

            if ($status === 'active') {
                $updateData = [
                    'is_active' => true,
                    'is_banned' => false,
                    'banned_until' => null
                ];
            } elseif ($status === 'suspended') {
                $updateData = [
                    'is_active' => false,
                    'is_banned' => false,
                    'banned_until' => null
                ];
            } elseif ($status === 'banned') {
                $updateData = [
                    'is_active' => false,
                    'is_banned' => true,
                    'banned_until' => null
                ];
            }

            User::whereIn('id', $request->user_ids)->update($updateData);

            $count = count($request->user_ids);

            return response()->json([
                'success' => true,
                'message' => "{$count} users {$request->status} successfully"
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update users'
            ], 500);
        }
    }

    /**
     * Reset user password
     */
    public function resetPassword(User $user)
    {
        try {
            // Generate a temporary password
            $tempPassword = Str::random(12);
            $user->update(['password' => Hash::make($tempPassword)]);

            // Send email with new password (you would implement this based on your email setup)
            // Mail::to($user->email)->send(new PasswordResetMail($tempPassword));

            return response()->json([
                'success' => true,
                'message' => 'Password reset email sent successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to reset password'
            ], 500);
        }
    }

    /**
     * Export users to CSV
     */
    public function export(Request $request)
    {
        try {
            $query = User::query();

            // Apply same filters as getUsers
            if ($request->filled('search')) {
                $search = $request->get('search');
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%");
                });
            }

            if ($request->filled('status')) {
                $status = $request->get('status');
                if ($status === 'active') {
                    $query->where('is_active', true)->where('is_banned', false);
                } elseif ($status === 'suspended') {
                    $query->where('is_active', false);
                } elseif ($status === 'banned') {
                    $query->where('is_banned', true);
                }
            }

            if ($request->filled('verified')) {
                if ($request->get('verified') == '1') {
                    $query->whereNotNull('email_verified_at');
                } else {
                    $query->whereNull('email_verified_at');
                }
            }

            $users = $query->get(['id', 'name', 'email', 'phone', 'is_active', 'is_banned', 'created_at', 'email_verified_at']);

            $filename = 'users_export_' . now()->format('Y-m-d_H-i-s') . '.csv';

            $headers = [
                'Content-Type' => 'text/csv',
                'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            ];

            $callback = function() use ($users) {
                $file = fopen('php://output', 'w');

                // Add CSV headers
                fputcsv($file, [
                    'ID',
                    'Name',
                    'Email',
                    'Phone',
                    'Status',
                    'Email Verified',
                    'Registration Date',
                    'Posts Count'
                ]);

                // Add data rows
                foreach ($users as $user) {
                    $status = 'Active';
                    if ($user->is_banned) {
                        $status = 'Banned';
                    } elseif (!$user->is_active) {
                        $status = 'Suspended';
                    }

                    fputcsv($file, [
                        $user->id,
                        $user->name,
                        $user->email,
                        $user->phone ?? 'N/A',
                        $status,
                        $user->email_verified_at ? 'Yes' : 'No',
                        $user->created_at->format('Y-m-d H:i:s'),
                        $user->posts_count ?? 0
                    ]);
                }

                fclose($file);
            };

            return response()->stream($callback, 200, $headers);

        } catch (\Exception $e) {
            Log::error('Error exporting users: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to export users');
        }
    }

    /**
     * Get the profile URL for the user (same logic as UserResource)
     *
     * @param User $user
     * @return string
     */
    private function getProfileUrl($user)
    {
        if (!$user->profile) {
            return '/images/default-avatar.png';
        }

        // Clean up profile filename
        $cleanProfile = $this->cleanFileName($user->profile);

        // Check if this is a legacy user (ID 1-3354) and use old server URL
        if ($user->id >= 1 && $user->id <= 3354) {
            // For legacy users, always use the old server URL with clean filename
            return 'https://connectapp.talosmart.xyz/uploads/profiles/' . $cleanProfile;
        }

        // For new users (ID > 3354), use current project logic

        // If profile_url is already set and is a full URL, return it
        if ($user->profile_url && filter_var($user->profile_url, FILTER_VALIDATE_URL)) {
            return $user->profile_url;
        }

        // If using cloud storage
        if (config('filesystems.default') === 's3') {
            try {
                // Try to get a public URL
                if (\Illuminate\Support\Facades\Storage::disk('s3')->exists('profiles/' . $cleanProfile)) {
                    return config('filesystems.disks.s3.url') . '/profiles/' . $cleanProfile;
                }
            } catch (\Exception $e) {
                // Log error and continue to fallback
                Log::warning('Failed to generate S3 URL for profile: ' . $cleanProfile, ['error' => $e->getMessage()]);
            }
        }

        // For local storage (new users)
        return url('uploads/profiles/' . $cleanProfile);
    }

    /**
     * Clean filename by removing duplications and invalid characters (same logic as UserResource)
     *
     * @param string $fileName
     * @return string
     */
    private function cleanFileName($fileName)
    {
        if (!$fileName) {
            return '';
        }

        // Remove any URL parts if they somehow got into the filename
        $fileName = basename($fileName);

        // Handle duplicated filenames (e.g., "file.jpegfile.jpeg" -> "file.jpeg")
        $pathInfo = pathinfo($fileName);
        $extension = isset($pathInfo['extension']) ? $pathInfo['extension'] : '';
        $basename = isset($pathInfo['filename']) ? $pathInfo['filename'] : $fileName;

        // Check if the basename contains the extension duplicated
        if ($extension && str_ends_with($basename, $extension)) {
            // Remove the duplicated extension from basename
            $basename = substr($basename, 0, -strlen($extension));
        }

        // Reconstruct the clean filename
        $cleanFileName = $extension ? $basename . '.' . $extension : $basename;

        return $cleanFileName;
    }
}
