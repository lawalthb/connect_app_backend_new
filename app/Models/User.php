<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes, HasRoles;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'username',
        'email',
        'password',
        'bio',
        'social_links',
        'profile',
        'profile_url',
        'avatar',
        'cover_photo',
        'birth_date',
        'gender',
        'country_id',
        'city',
        'state',
        'postal_code',
        'timezone',
        'latitude',
        'longitude',
        'privacy_public_profile',
        'privacy_show_online_status',
        'privacy_show_activity',
        'notification_email',
        'notification_push',
        'notification_preferences',
        'language',
        'is_advertiser',
        'interests',
        'skills',
        'occupation',
        'education_level',
        'relationship_status',
        'has_children',
        'income_range',
        'device_token',
        'social_id',
        'social_type',
        'is_active',
        'is_verified',
        'reset_otp',
        'email_otp',
        'is_banned',
        'ban_reason',
        'banned_until',
         'email_otp_expires_at',
         'deleted_at',
        'deleted_flag',
        'registration_step'
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'reset_otp',
        'email_otp',
        'device_token',
        'last_login_ip',
        'ban_reason',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'last_login_at' => 'datetime',
        'last_activity_at' => 'datetime',
        'birth_date' => 'date',
        'verified_at' => 'datetime',
        'banned_until' => 'datetime',
        'social_links' => 'array',
        'interests' => 'array',
        'skills' => 'array',
        'notification_preferences' => 'array',
        'latitude' => 'float',
        'longitude' => 'float',
        'privacy_public_profile' => 'boolean',
        'privacy_show_online_status' => 'boolean',
        'privacy_show_activity' => 'boolean',
        'notification_email' => 'boolean',
        'notification_push' => 'boolean',
        'is_advertiser' => 'boolean',
        'has_children' => 'boolean',
        'is_online' => 'boolean',
        'is_active' => 'boolean',
        'is_verified' => 'boolean',
        'is_banned' => 'boolean',
        'deleted_flag' => 'string',
        'deleted_at' => 'datetime',
    ];


     /**
     * Get the full profile URL
     *
     * @return string
     */
    public function getProfileUrlAttribute()
    {
        if (empty($this->profile)) {
            return null;
        }

        // Check if the profile is already a full URL
        if (filter_var($this->profile, FILTER_VALIDATE_URL)) {
            return $this->profile;
        }

        // Check if the profile is a video file
        $videoExtensions = ['mp4', 'mov', 'avi', 'wmv', 'flv', 'webm'];
        $extension = pathinfo($this->profile, PATHINFO_EXTENSION);
        $isVideo = in_array(strtolower($extension), $videoExtensions);

        // Base path for profiles
        $basePath = 'uploads/profiles/';

        // Full URL with domain
        $baseUrl = url('/');

        // If using S3 or other cloud storage
        if (config('filesystems.default') === 's3') {
            return Storage::disk('s3')->url($basePath . $this->profile);
        }

        // For local storage
        return $baseUrl . '/' . $basePath . $this->profile;
    }

     public function getAvatarUrlAttribute()
    {
        if (empty($this->avatar)) {
            // Return default avatar
            return url('/images/default-avatar.png');
        }

        // Check if the avatar is already a full URL
        if (filter_var($this->avatar, FILTER_VALIDATE_URL)) {
            return $this->avatar;
        }

        // Base path for avatars
        $basePath = 'uploads/avatars/';

        // Full URL with domain
        $baseUrl = url('/');

        // If using S3 or other cloud storage
        if (config('filesystems.default') === 's3') {
            return Storage::disk('s3')->url($basePath . $this->avatar);
        }

        // For local storage
        return $baseUrl . '/' . $basePath . $this->avatar;
    }



    /**
     * The "booted" method of the model.
     *
     * @return void
     */
    protected static function booted()
    {
        static::creating(function ($user) {
            if (auth()->check()) {
                $user->created_by = auth()->id();
            }
        });

        static::updating(function ($user) {
            if (auth()->check()) {
                $user->updated_by = auth()->id();
            }
        });

        // static::deleting(function ($user) {
        //     if (auth()->check()) {
        //         $user->deleted_by = auth()->id();
        //         $user->deleted_flag = 'Y';
        //         $user->save();
        //     }
        // });
    }

    /**
     * Get the country that the user belongs to.
     */
    public function country()
    {
        return $this->belongsTo(Country::class);
    }

    /**
     * Get the social circles that belong to the user.
     */
    public function socialCircles()
    {
        return $this->belongsToMany(SocialCircle::class, 'user_social_circles', 'user_id', 'social_id')
            ->withTimestamps()
            ->withPivot(['deleted_at', 'deleted_flag'])
            ->wherePivot('deleted_flag', 'N')
            ->where('social_circles.deleted_flag', 'N')

            ->where('social_circles.is_active', true)
            ->whereNull('social_circles.deleted_at');
    }

    /**
     * Get the ads that belong to the user.
     */
    public function ads()
    {
        return $this->hasMany(\App\Models\Ad::class);
    }

    /**
     * Get active social circles for the user (with proper scoping).
     */
    public function activeSocialCircles()
    {
        return $this->belongsToMany(SocialCircle::class, 'user_social_circles', 'user_id', 'social_id')
            ->withTimestamps()
            ->withPivot(['deleted_at', 'deleted_flag'])
            ->wherePivot('deleted_flag', 'N')
            ->where('social_circles.is_active', 1)
            ->where('social_circles.deleted_flag', 'N');
    }

    /**
     * Get the posts that belong to the user.
     */
    public function posts()
    {
        return $this->hasMany(Post::class);
    }

    /**
     * Get the user's advertisements.
     */
    public function advertisements()
    {
        return $this->hasMany(Advertisement::class, 'advertiser_id');
    }

    /**
     * Get the user's followers.
     */
    public function followers()
    {
        return $this->belongsToMany(User::class, 'followers', 'followed_id', 'follower_id')
            ->withTimestamps();
    }

    /**
     * Get the users that this user is following.
     */
    public function following()
    {
        return $this->belongsToMany(User::class, 'followers', 'follower_id', 'followed_id')
            ->withTimestamps();
    }

    /**
     * Get the full profile image URL.
     *
     * @return string|null
     */
    public function getProfileFullUrlAttribute()
    {
        if (!$this->profile) {
            return null;
        }

        $base = $this->profile_url ? url($this->profile_url) : '';
        return $base ? "{$base}/{$this->profile}" : asset("storage/profile/{$this->profile}");
    }

    /**
     * Get the user's age.
     *
     * @return int|null
     */
    public function getAgeAttribute()
    {
        return $this->birth_date ? Carbon::parse($this->birth_date)->age : null;
    }

    /**
     * Check if user belongs to a specific social circle.
     *
     * @param int $socialCircleId
     * @return bool
     */
    public function belongsToSocialCircle($socialCircleId)
    {
        return $this->socialCircles()->where('social_circles.id', $socialCircleId)->exists();
    }

    /**
     * Add user to a social circle.
     *
     * @param int $socialCircleId
     * @return \App\Models\UserSocialCircle
     */
    public function addToSocialCircle($socialCircleId)
    {
        // Check if already exists and soft deleted
        $existing = UserSocialCircle::withTrashed()
            ->where('user_id', $this->id)
            ->where('social_id', $socialCircleId)
            ->first();

        if ($existing) {
            // If soft deleted, restore it
            if ($existing->trashed() || $existing->deleted_flag === 'Y') {
                $existing->restore();
                $existing->deleted_flag = 'N';
                $existing->deleted_by = null;
                $existing->save();
                return $existing;
            }
            return $existing;
        }

        // Create new relationship
        return UserSocialCircle::create([
            'user_id' => $this->id,
            'social_id' => $socialCircleId,
        ]);
    }

    /**
     * Remove user from a social circle.
     *
     * @param int $socialCircleId
     * @return bool
     */
    public function removeFromSocialCircle($socialCircleId)
    {
        $pivot = UserSocialCircle::where('user_id', $this->id)
            ->where('social_id', $socialCircleId)
            ->first();

        if ($pivot) {
            $pivot->deleted_flag = 'Y';
            $pivot->save();
            $pivot->delete();
            return true;
        }

        return false;
    }

    /**
     * Update last login information.
     *
     * @param string|null $ip
     * @return $this
     */
    public function updateLastLogin($ip = null)
    {
        $this->last_login_at = now();
        $this->last_login_ip = $ip ?: request()->ip();
        $this->login_count += 1;
        $this->is_online = true;
        $this->save();

        return $this;
    }

    /**
     * Update last activity timestamp.
     *
     * @return $this
     */
    public function updateLastActivity()
    {
        $this->last_activity_at = now();
        $this->is_online = true;
        $this->save();

        return $this;
    }

    /**
     * Set user offline.
     *
     * @return $this
     */
    public function setOffline()
    {
        $this->is_online = false;
        $this->save();

        return $this;
    }

    /**
     * Scope a query to only include active users.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeActive($query)
    {
        return $query->where('users.is_active', true)
            ->where('users.deleted_flag', 'N')
            ->where(function($q) {
                $q->where('is_banned', false)
                    ->orWhere(function($query) {
                        $query->where('is_banned', true)
                            ->where('banned_until', '<', now());
                    });
            });
    }

    /**
     * Scope a query to include users within a certain age range.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  int  $minAge
     * @param  int  $maxAge
     * @return \Illuminate\Database\Eloquent
 * Scope a query to include users within a certain age range.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  int  $minAge
     * @param  int  $maxAge
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeAgeRange($query, $minAge, $maxAge)
    {
        $minDate = Carbon::now()->subYears($maxAge)->format('Y-m-d');
        $maxDate = Carbon::now()->subYears($minAge)->format('Y-m-d');

        return $query->whereBetween('birth_date', [$minDate, $maxDate]);
    }

    /**
     * Scope a query to only include users of a specific gender.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  string|array  $gender
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeGender($query, $gender)
    {
        if (is_array($gender)) {
            return $query->whereIn('gender', $gender);
        }

        return $query->where('gender', $gender);
    }

    /**
     * Scope a query to only include users from a specific country.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  int|array  $countryId
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeCountry($query, $countryId)
    {
        if (is_array($countryId)) {
            return $query->whereIn('country_id', $countryId);
        }

        return $query->where('country_id', $countryId);
    }

    /**
     * Scope a query to include users within a certain distance.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  float  $lat
     * @param  float  $lng
     * @param  int  $distance
     * @param  string  $unit (km or mi)
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeNearby($query, $lat, $lng, $distance = 10, $unit = 'km')
    {
        $radius = $unit == 'mi' ? 3959 : 6371;

        return $query->selectRaw("*,
            ($radius * acos(cos(radians($lat)) * cos(radians(latitude)) *
            cos(radians(longitude) - radians($lng)) +
            sin(radians($lat)) * sin(radians(latitude)))) AS distance")
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->having('distance', '<=', $distance)
            ->orderBy('distance');
    }

    /**
     * Scope a query to only include users with specific interests.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  array  $interests
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeWithInterests($query, array $interests)
    {
        return $query->where(function ($query) use ($interests) {
            foreach ($interests as $interest) {
                $query->orWhereJsonContains('interests', $interest);
            }
        });
    }

    /**
     * Scope a query to only include users who belong to specific social circles.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  int|array  $socialCircleIds
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeInSocialCircles($query, $socialCircleIds)
    {
        return $query->whereHas('socialCircles', function ($query) use ($socialCircleIds) {
            if (is_array($socialCircleIds)) {
                $query->whereIn('social_circles.id', $socialCircleIds);
            } else {
                $query->where('social_circles.id', $socialCircleIds);
            }
        });
    }

    /**
     * Scope a query to only include advertisers.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeAdvertisers($query)
    {
        return $query->where('is_advertiser', true);
    }

    /**
     * Scope a query to only include verified users.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeVerified($query)
    {
        return $query->where('is_verified', true);
    }

    /**
     * Generate OTP for password reset.
     *
     * @return string
     */
    public function generateResetOTP()
    {
        $otp = sprintf("%04d", mt_rand(1000, 9999));
        $this->reset_otp = $otp;
        $this->save();

        return $otp;
    }

    /**
     * Generate OTP for email verification.
     *
     * @return string
     */
    public function generateEmailOTP()
    {
        $otp = sprintf("%04d", mt_rand(1000, 9999));
        $this->email_otp = $otp;
        $this->save();

        return $otp;
    }

    /**
     * Verify user's email.
     *
     * @return $this
     */
    public function verifyEmail()
    {
        $this->email_verified_at = now();
        $this->is_verified = true;
        $this->verified_at = now();
        $this->email_otp = null;
        $this->save();

        return $this;
    }

    /**
     * Check if user has been active recently.
     *
     * @param int $minutes
     * @return bool
     */
    public function isActiveRecently($minutes = 15)
    {
        if (!$this->last_activity_at) {
            return false;
        }

        return $this->last_activity_at->gt(now()->subMinutes($minutes));
    }

    /**
     * Get user's full name or username.
     *
     * @return string
     */
    public function getDisplayNameAttribute()
    {
        return $this->name ?: $this->username;
    }

    /**
     * Get user's location string.
     *
     * @return string|null
     */
    public function getLocationStringAttribute()
    {
        $parts = [];

        if ($this->city) {
            $parts[] = $this->city;
        }

        if ($this->state) {
            $parts[] = $this->state;
        }

        if ($this->country && $this->country->name) {
            $parts[] = $this->country->name;
        }

        return count($parts) > 0 ? implode(', ', $parts) : null;
    }

    /**
     * Check if the user has a complete profile.
     *
     * @return bool
     */
    public function hasCompleteProfile()
    {
        return $this->name &&
               $this->email_verified_at &&
               $this->profile &&
               $this->birth_date &&
               $this->gender &&
               $this->country_id;
    }

    /**
     * Get profile completion percentage.
     *
     * @return int
     */
    public function getProfileCompletionAttribute()
    {
        $fields = [
            'name' => 10,
            'email_verified_at' => 10,
            'username' => 10,
            'bio' => 10,
            'profile' => 15,
            'country_id' => 10,
            'phone' => 5,
            'birth_date' => 5,
            'gender' => 5,
            'city' => 5,
            'state' => 5,
            'interests' => 5,
            'social_links' => 5,
        ];

        $total = 0;
        foreach ($fields as $field => $weight) {
            if ($field === 'email_verified_at' && $this->$field) {
                $total += $weight;
            } elseif ($field === 'interests' && is_array($this->$field) && count($this->$field) > 0) {
                $total += $weight;
            } elseif ($field === 'social_links' && is_array($this->$field) && count($this->$field) > 0) {
                $total += $weight;
            } elseif (!empty($this->$field)) {
                $total += $weight;
            }
        }

        return min(100, $total);
    }

    /**
     * Ban user.
     *
     * @param string|null $reason
     * @param \DateTime|null $until
     * @return $this
     */
    public function ban($reason = null, $until = null)
    {
        $this->is_banned = true;
        $this->ban_reason = $reason;
        $this->banned_until = $until;
        $this->save();

        return $this;
    }

    /**
     * Unban user.
     *
     * @return $this
     */
    public function unban()
    {
        $this->is_banned = false;
        $this->ban_reason = null;
        $this->banned_until = null;
        $this->save();

        return $this;
    }

    /**
     * Check if user is currently banned.
     *
     * @return bool
     */
    public function isBanned()
    {
        if (!$this->is_banned) {
            return false;
        }

        if ($this->banned_until && now()->gt($this->banned_until)) {
            $this->unban();
            return false;
        }

        return true;
    }

    public function routeNotificationForFcm()
    {
        return $this->device_token;
    }
    /**
 * Get the profile uploads for the user.
 */
public function profileUploads()
{
    return $this->hasMany(UserProfileUpload::class)
                ->where('deleted_flag', 'N');
}

    /**
     * Get conversations where user is a participant
     */
    public function conversations()
    {
        return $this->belongsToMany(Conversation::class, 'conversation_participants')
                    ->withPivot(['role', 'joined_at', 'left_at', 'last_read_at', 'is_active'])
                    ->wherePivot('is_active', true)
                    ->orderBy('last_message_at', 'desc');
    }

    /**
     * Get messages sent by user
     */
    public function messages()
    {
        return $this->hasMany(Message::class);
    }

    /**
     * Get the user's timezone
     *
     * @return string
     */
    public function getTimezone(): string
    {
        $userTimezone = $this->timezone;

        // Fallback to application's default timezone if user's timezone is not set or invalid
        if (empty($userTimezone) || !in_array($userTimezone, timezone_identifiers_list())) {
            return config('app.timezone', 'UTC');
        }
        return $userTimezone;
    }

    /**
     * Convert a date to user's timezone
     *
     * @param $date
     * @return Carbon
     */
    public function convertToUserTimezone($date): Carbon
    {
        return Carbon::parse($date)->setTimezone($this->getTimezone());
    }

    /**
     * Get formatted date in user's timezone
     *
     * @param $date
     * @param string $format
     * @return string
     */
    public function formatInUserTimezone($date, string $format = 'Y-m-d H:i:s'): string
    {
        return $this->convertToUserTimezone($date)->format($format);
    }

    /**
     * Get human readable date in user's timezone
     *
     * @param $date
     * @return string
     */
    public function humanDateInUserTimezone($date): string
    {
        return $this->convertToUserTimezone($date)->diffForHumans();
    }

    /**
     * Get calls initiated by this user
     */
    public function initiatedCalls()
    {
        return $this->hasMany(Call::class, 'initiated_by');
    }

    /**
     * Get call participations
     */
    public function callParticipations()
    {
        return $this->hasMany(CallParticipant::class);
    }

    /**
     * Get calls this user participated in
     */
    public function calls()
    {
        return $this->hasManyThrough(Call::class, CallParticipant::class, 'user_id', 'id', 'id', 'call_id');
    }

    public function stories()
    {
        return $this->hasMany(Story::class);
    }

    public function activeStories()
    {
        return $this->hasMany(Story::class)->active()->orderBy('created_at', 'desc');
    }

    public function storyViews()
    {
        return $this->hasMany(StoryView::class, 'viewer_id');
    }

    /**
     * Connection related relationships
     */
    public function sentRequests()
    {
        return $this->hasMany(UserRequest::class, 'sender_id');
    }

    public function receivedRequests()
    {
        return $this->hasMany(UserRequest::class, 'receiver_id');
    }

    public function connections()
    {
        return $this->belongsToMany(User::class, 'user_requests', 'sender_id', 'receiver_id')
                    ->wherePivot('status', 'accepted')
                    ->wherePivot('sender_status', 'accepted')
                    ->wherePivot('receiver_status', 'accepted')
                    ->withPivot(['status', 'sender_status', 'receiver_status', 'created_at']);
    }

    public function likesGiven()
    {
        return $this->hasMany(UserLike::class, 'user_id');
    }

    public function likesReceived()
    {
        return $this->hasMany(UserLike::class, 'liked_user_id');
    }

    public function swipeStats()
    {
        return $this->hasMany(UserSwipe::class);
    }

    public function profileImages()
    {
        return $this->hasMany(UserProfileUpload::class)->where('deleted_flag', 'N');
    }

    // public function socialCircles()
    // {
    //     return $this->belongsToMany(SocialCircle::class, 'user_social_circles', 'user_id', 'social_id')
    //                 ->where('deleted_flag', 'N');
    // }

    /**
     * Check if user is connected to another user
     */
    public function isConnectedTo($userId): bool
    {
        return UserRequest::where(function ($query) use ($userId) {
            $query->where(['sender_id' => $this->id, 'receiver_id' => $userId])
                  ->orWhere(['sender_id' => $userId, 'receiver_id' => $this->id]);
        })
        ->where('status', 'accepted')
        ->where('sender_status', 'accepted')
        ->where('receiver_status', 'accepted')
        ->exists();
    }

    /**
     * Check if user has liked another user
     */
    public function hasLiked($userId): bool
    {
        return $this->likesGiven()
                    ->where('liked_user_id', $userId)
                    ->where('is_active', true)
                    ->exists();
    }

    /**
     * Get connection status with another user
     */
    public function getConnectionStatusWith($userId): string
    {
        $request = UserRequest::where(['sender_id' => $this->id, 'receiver_id' => $userId])->first();
        $reverseRequest = UserRequest::where(['sender_id' => $userId, 'receiver_id' => $this->id])->first();

        if ($request) {
            return $request->status;
        } elseif ($reverseRequest) {
            return $reverseRequest->status === 'pending' ? 'received_request' : $reverseRequest->status;
        }

        return 'none';
    }

     /**
     * Get all profile images for the user (from both tables)
     */
    public function getAllProfileImages()
    {
        $profileMultiUploads = $this->hasMany(UserProfileUpload::class)
            ->where('deleted_flag', 'N')
            ->get()
            ->map(function ($upload) {
                return [
                    'id' => $upload->id,
                    'type' => 'profile_multi',
                    'filename' => $upload->profile,
                    'url' => $upload->profile_url . $upload->profile,
                    'full_url' => url($upload->profile_url . $upload->profile),
                    'is_main' => $this->profile === $upload->profile,
                    'created_at' => $upload->created_at,
                    'updated_at' => $upload->updated_at,
                ];
            });

        $userProfileUploads = $this->profileUploads
            ->map(function ($upload) {
                return [
                    'id' => $upload->id,
                    'type' => 'user_profile',
                    'filename' => $upload->file_name,
                    'url' => $upload->file_url,
                    'full_url' => url($upload->file_url),
                    'file_type' => $upload->file_type,
                    'is_main' => $this->profile === $upload->file_name,
                    'created_at' => $upload->created_at,
                    'updated_at' => $upload->updated_at,
                ];
            });

        return $profileMultiUploads->concat($userProfileUploads)
            ->sortByDesc('created_at')
            ->values();
    }

    /**
     * Get profile multi uploads relationship
     */
    public function profileMultiUploads()
    {
        return $this->hasMany(UserProfileUpload::class)
                    ->where('deleted_flag', 'N');
    }

}
