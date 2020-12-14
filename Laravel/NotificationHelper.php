<?php

namespace App\Helpers;

use App\Mail\NotificationEmail;
use App\Mail\NotificationEmailCalendar;
use App\Mail\RegistrationEmail;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;

class NotificationHelper
{
    /**
     * @param array $data
     *
     * @return bool
     */
    public static function create($data): bool
    {
        $isFailed = Validator::make(
            $data,
            [
                'user_id' => ['required', 'exists:users,id'],
                'notification_type' => ['required'],
                'data' => ['required'],
            ]
        )->fails();
        if (!$isFailed) {
            $notification = new Notification();
            $notification->fill($data);
            if ($notification->save()) {
                return true;
            }
        }
        return false;
    }

    /**
     * @param array $data
     */
    public static function approveEngagement($data): void
    {
        $data['notification_type'] = 'closeEngagement';
        $data['data']['caption'] = 'Engagement was approved';
        self::create($data);

        /** @var User $userFirst */
        $userFirst = User::query()->where('id', $data['user_id1'])->first();
        /** @var User $userSecond */
        $userSecond = User::query()->where('id', $data['user_id2'])->first();

        $message = 'Engagement was approved.';
        $urlGoogle = $data['data']['engagement']->google_calendar_link;
        $urlIcs = $data['data']['engagement']->ics_link;

        Mail::to($userFirst->email)->send(
            new NotificationEmailCalendar(
                [
                    'title' => $data['data']['caption'],
                    'message' => $message,
                    'url_google' => $urlGoogle,
                    'url_ics' => $urlIcs,
                ]
            )
        );
        Mail::to($userSecond->email)->send(
            new NotificationEmailCalendar(
                [
                    'title' => $data['data']['caption'],
                    'message' => $message,
                    'url_google' => $urlGoogle,
                    'url_ics' => $urlIcs,
                ]
            )
        );
    }

    /**
     * @param array $data
     */
    public static function newEngagement($data): void
    {
        $data['notification_type'] = 'newEngagement';
        $data['data']['caption'] = 'You have an engagement request.';
        self::create($data);

        /** @var User $user */
        $user = User::query()->where('id', $data['user_id'])->first();
        $message = 'You have an engagement request. Please review and respond as soon as possible.';
        $url = config('app.url_front') . '/login';
        Mail::to($user->email)->send(
            new NotificationEmail(['title' => $data['data']['caption'], 'message' => $message, 'url' => $url])
        );
    }

    /**
     * @param array $data
     */
    public static function reviewEngagement($data): void
    {
        $data['notification_type'] = 'reviewEngagement';
        $data['data']['caption'] = 'Your engagement has been updated.';
        self::create($data);

        /** @var User $user */
        $user = User::query()->where('id', $data['user_id'])->first();
        $message = 'Your engagement has been updated. Please review as soon as possible.';
        $url = config('app.url_front') . '/login';
        Mail::to($user->email)->send(
            new NotificationEmail(['title' => $data['data']['caption'], 'message' => $message, 'url' => $url])
        );
    }

    /**
     * @param array $data
     */
    public static function paymentSubscriptionSuccess($data): void
    {
        $data['notification_type'] = 'paymentSubscriptionSuccess';
        $data['data']['caption'] = 'Your subscription payment was succeeded.';
        self::create($data);
    }

    /**
     * @param array $data
     */
    public static function paymentEngagementSuccess($data): void
    {
        $data['notification_type'] = 'paymentEngagementSuccess';
        $data['data']['caption'] = 'Your payment for the engagement was successful.';
        self::create($data);
    }

    /**
     * @param array $data
     */
    public static function cancelEngagement($data): void
    {
        $data['notification_type'] = 'cancelEngagement';
        $data['data']['caption'] = 'Your engagement has been canceled.';
        self::create($data);

        /** @var User $user */
        $user = User::query()->where('id', $data['user_id'])->first();
        $message = 'Your engagement has been cancelled. Please review or reschedule as needed.';
        $url = config('app.url_front') . '/login';
        Mail::to($user->email)->send(
            new NotificationEmail(['title' => $data['data']['caption'], 'message' => $message, 'url' => $url])
        );
    }

    /**
     * @param array $data
     */
    public static function closeEngagement($data): void
    {
        $data['notification_type'] = 'closeEngagement';
        $data['data']['caption'] = 'Was your engagement completed?';
        self::create($data);

        /** @var User $user */
        $user = User::query()->where('id', $data['user_id'])->first();
        $message = 'Was your engagement completed? Please confirm as soon as possible.';
        $url = config('app.url_front') . '/login';
        Mail::to($user->email)->send(
            new NotificationEmail(['title' => $data['data']['caption'], 'message' => $message, 'url' => $url])
        );
    }
}
