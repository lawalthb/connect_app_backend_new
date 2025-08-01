<?php

use App\Http\Controllers\API\V1\AdController;
use App\Http\Controllers\API\V1\AuthController;
use App\Http\Controllers\API\V1\SubscriptionController;
use Illuminate\Support\Facades\Route;
use App\Helpers\StorageUploadHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

Route::get('/', function () {
    return view('welcome');
});

// Fallback login route (redirects to admin login)
Route::get('/login', function () {
    return redirect()->route('admin.auth.login');
})->name('login');

// Test route for live streaming functionality
Route::get('/test-streaming', function () {
    return view('test-streaming');
});

// Debug route for file upload testing
Route::get('/test-upload', function () {
    return view('test-upload');
});

Route::post('/test-upload', function (Request $request) {
    try {
        if ($request->hasFile('profile_image')) {
            $file = $request->file('profile_image');

            Log::info('Test upload started', [
                'original_name' => $file->getClientOriginalName(),
                'size' => $file->getSize(),
                'mime_type' => $file->getMimeType(),
                'is_valid' => $file->isValid()
            ]);

            $result = StorageUploadHelper::uploadFile($file, 'profiles');

            return response()->json([
                'success' => true,
                'message' => 'File uploaded successfully',
                'data' => $result
            ]);
        } else {
            return response()->json([
                'success' => false,
                'message' => 'No file provided'
            ]);
        }
    } catch (\Exception $e) {
        Log::error('Test upload failed', [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);

        return response()->json([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
});

// Simple test route (no external CDNs)
Route::get('/simple-streaming-test', function () {
    return view('simple-streaming-test');
});

// Debug route
Route::get('/debug-streaming', function () {
    return view('debug-streaming');
});

// Public API endpoint for stream data (bypasses API middleware)
Route::get('/api/streams/latest', function () {
    try {
        $controller = new \App\Http\Controllers\API\V1\StreamController();
        $request = request();
        return $controller->latest($request);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Error fetching streams: ' . $e->getMessage()
        ], 500);
    }
});

// Public API endpoint for viewer count (bypasses API middleware)
// Note: Temporarily disabled due to routing conflicts
// Route::get('/api/streams/{id}/viewers', function ($id) {
//     try {
//         $controller = new \App\Http\Controllers\API\V1\StreamController();
//         $request = request();
//         return $controller->viewers($request, $id);
//     } catch (\Exception $e) {
//         return response()->json([
//             'success' => false,
//             'message' => 'Error fetching viewers: ' . $e->getMessage()
//         ], 500);
//     }
// });

// Public API endpoint for getting viewer tokens (bypasses API middleware)
Route::post('/api/streams/viewer-token', function () {
    try {
        $channelName = request('channel_name');
        $uid = request('uid', null);

        if (!$channelName) {
            return response()->json([
                'success' => false,
                'message' => 'Channel name is required'
            ], 400);
        }

        // Generate a random UID if not provided
        if (!$uid) {
            $uid = rand(100000, 999999);
        }

        // Initialize AgoraHelper
        \App\Helpers\AgoraHelper::init();

        // Generate token for viewer (subscriber role)
        $token = \App\Helpers\AgoraHelper::generateRtcToken($channelName, (int)$uid, 3600, 'subscriber');

        if (!$token) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate token'
            ], 500);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'token' => $token,
                'app_id' => \App\Helpers\AgoraHelper::getAppId(),
                'channel_name' => $channelName,
                'uid' => $uid,
                'expires_in' => 3600
            ]
        ]);

    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Error generating token: ' . $e->getMessage()
        ], 500);
    }
});

// Watch live stream by user ID
Route::get('/watch/{userId}', function ($userId) {
    return view('watch-stream', compact('userId'));
});

// Watch live stream (enhanced mobile version with user ID)
Route::get('/stream/{streamId}/watch/{userId}', function ($streamId, $userId) {
    $stream = \App\Models\Stream::with('user')->findOrFail($streamId);

    // Get user from database for authentication simulation
    $user = \App\Models\User::find($userId);

    $hasPaid = false;
    $canChat = false;

    if ($user) {
        $canChat = true; // User exists, can chat

        if ($stream->price > 0) {
            $hasPaid = $stream->hasUserPaid($user);
        } else {
            $hasPaid = true; // Free stream
        }
    } else {
        // Guest user - can watch free streams but can't chat
        $hasPaid = ($stream->price == 0);
        $canChat = false;
    }

    return view('stream.watch-mobile', compact('stream', 'hasPaid', 'canChat', 'user', 'userId'));
});

// Watch live stream (enhanced mobile version - fallback without user ID)
Route::get('/stream/{streamId}/watch', function ($streamId) {
    $stream = \App\Models\Stream::with('user')->findOrFail($streamId);

    $hasPaid = false;
    $canChat = false;
    $user = null;
    $userId = null;

    if (auth()->check()) {
        $user = auth()->user();
        $userId = $user->id;
        $canChat = true;

        if ($stream->price > 0) {
            $hasPaid = $stream->hasUserPaid($user);
        } else {
            $hasPaid = true;
        }
    } else {
        // Guest user - can only watch free streams
        $hasPaid = ($stream->price == 0);
    }

    return view('stream.watch-mobile', compact('stream', 'hasPaid', 'canChat', 'user', 'userId'));
});

// Original watch page (desktop version)
Route::get('/stream/{streamId}/watch-desktop', function ($streamId) {
    $stream = \App\Models\Stream::with('user')->findOrFail($streamId);

    $hasPaid = false;
    if (auth()->check() && $stream->price > 0) {
        $hasPaid = $stream->hasUserPaid(auth()->user());
    } elseif ($stream->price == 0) {
        $hasPaid = true;
    }

    return view('stream.watch', compact('stream', 'hasPaid'));
});

Route::get('/test-mail', function () {
    try {
        Mail::raw('Test email from ConnectApp', function ($message) {
            $message->to('lawalthb@gmail.com')
                    ->subject('Test Email');
        });
        return 'Email sent successfully!';
    } catch (\Exception $e) {
        return 'Email error: ' . $e->getMessage();
    }
});
// Google OAuth routes - moved to web middleware
Route::get('api/v1/auth/google', [AuthController::class, 'redirectToGoogle']);
Route::get('api/v1/auth/google/callback', [AuthController::class, 'handleGoogleCallback']);





Route::get('/payment/callback', [SubscriptionController::class, 'handleNombaCallbackWeb'])->name('payment.callback.web');


//for ads redirection
    Route::get('/payment/{payment}/success', [AdController::class, 'paymentSuccess'])
        ->name('ads.payment.success');
    Route::get('/payment/{payment}/cancel', [AdController::class, 'paymentCancel'])
        ->name('ads.payment.cancel');
