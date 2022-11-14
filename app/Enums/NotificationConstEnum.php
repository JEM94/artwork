<?php

namespace App\Enums;

class NotificationConstEnum
{
    public const NOTIFICATION_ROOM_REQUEST = 'ROOM_REQUEST';
    public const NOTIFICATION_CONFLICT = 'NOTIFICATION_CONFLICT';
    public const NOTIFICATION_EVENT_CHANGED = 'NOTIFICATION_EVENT_CHANGED';
    public const NOTIFICATION_LOUD_ADJOINING_EVENT = 'NOTIFICATION_LOUD_ADJOINING_EVENT';

    public const NOTIFICATION_UPSERT_ROOM_REQUEST = 'NOTIFICATION_UPSERT_ROOM_REQUEST';
    public const NOTIFICATION_REMINDER_ROOM_REQUEST = 'NOTIFICATION_REMINDER_ROOM_REQUEST';
    public const NOTIFICATION_ROOM_CHANGED = 'NOTIFICATION_ROOM_CHANGED';

    public const NOTIFICATION_NEW_TASK = 'NOTIFICATION_NEW_TASK';
    public const NOTIFICATION_TASK_REMINDER = 'TASK_REMINDER';
    public const NOTIFICATION_TASK_CHANGED = 'TASK_CHANGED';

    public const NOTIFICATION_PROJECT = 'NOTIFICATION_PROJECT';
    public const NOTIFICATION_TEAM = 'NOTIFICATION_TEAM';

    public const NOTIFICATION_DEADLINE = 'NOTIFICATION_DEADLINE';
}
