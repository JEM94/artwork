<?php

namespace Artwork\Core\Console\Commands;

use Artwork\Modules\GeneralSettings\Models\GeneralSettings;
use Artwork\Modules\Notification\Enums\NotificationFrequencyEnum;
use Artwork\Modules\Notification\Enums\NotificationGroupEnum;
use Artwork\Modules\Notification\Mail\NotificationSummary;
use Artwork\Modules\User\Models\User;
use Dotenv\Dotenv;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class SendNotificationsEmailSummariesCommand extends Command
{
    protected $signature = 'artwork:send-notifications-email-summaries {frequency=daily}';

    protected $description = 'Sends summaries of notifications to all users.';

    private readonly array $env;

    public function __construct(private readonly GeneralSettings $generalSettings)
    {
        parent::__construct();

        $this->env = Dotenv::parse(
            file_get_contents(base_path('.env'))
        );
    }

    public function handle(): int
    {
        $frequency = str_contains(($frequency = $this->argument('frequency')), '=') ?
            explode('=', $frequency)[1] :
            $frequency;

        if (is_null(NotificationFrequencyEnum::tryFrom($frequency))) {
            $this->error('Argument "frequency" must be type of ' . NotificationFrequencyEnum::class);

            return 1;
        }

        User::all()->each(fn (User $user) => $this->sendNotificationsSummary($user, $frequency));

        return 0;
    }

    protected function sendNotificationsSummary(User $user, string $frequency): void
    {
        $typesOfUser = $user->notificationSettings()
            ->where('frequency', $frequency)
            ->where('enabled_email', true)
            ->pluck("type");

        $notificationClasses = $typesOfUser->map(function ($type) {
            return $type->notificationClass();
        });

        $notifications = $user->notifications()
            ->whereNull('read_at')
            ->whereIn('type', $notificationClasses->unique())
            ->whereDate('created_at', '>=', now()->subWeeks(2))
            ->get()
            ->groupBy(function ($notification) {
                return $notification['data']['groupType'];
            });

        $notificationArray = [];
        foreach ($notifications as $notification) {
            $count = 1;
            foreach ($notification as $notificationBody) {
                $notificationArray[$notificationBody->data['groupType']] = [
                    'title' => __(
                        'notification-group-enum.title.' .
                        NotificationGroupEnum::from($notificationBody->data['groupType'])->title(),
                        [],
                        'de'
                    ),
                    'count' => $count++,
                ];
            }
            foreach ($notification as $notificationBody) {
                $notificationArray[$notificationBody->data['groupType']]['notifications'][] = [
                    'body' => $notificationBody->data
                ];
            }
        }

        if (!empty($notificationArray)) {
            Mail::to($user)->send(
                new NotificationSummary(
                    $notificationArray,
                    $user->first_name,
                    $this->generalSettings->page_title,
                    $this->env['SYSTEM_MAIL']
                )
            );
        }
    }
}
