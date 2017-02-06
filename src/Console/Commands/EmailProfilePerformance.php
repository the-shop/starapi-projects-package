<?php

namespace TheShop\Projects\Console\Commands;

use TheShop\Helpers\MailSend;
use App\Profile;
use App\Services\ProfilePerformance;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;
use Carbon\Carbon;

/**
 * Class EmailProfilePerformance
 * @package App\Console\Commands
 */
class EmailProfilePerformance extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'email:profile:performance {daysAgo : How many days before time of command execution} 
    {--accountants : If option is passed, value is true, send email to admins and accountants with salary report}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Aggregates user performance and sends out emails';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $performance = new ProfilePerformance();

        $profiles = Profile::all();

        $daysAgo = $this->argument('daysAgo');
        $unixNow = (new \DateTime())->format('U');
        $unixAgo = $unixNow - (int)$daysAgo * 24 * 60 * 60;

        $forAccountants = $this->option('accountants');

        //if option is passed set date range from 1st day of current month until last day of current month
        if ($forAccountants) {
            $unixNow = Carbon::now()->endOfMonth()->format('U');
            $unixAgo = Carbon::now()->firstOfMonth()->format('U');
        }

        $adminAggregation = [];

        foreach ($profiles as $profile) {
            if ($forAccountants && !$profile->employee) {
                continue;
            }

            $data = $performance->aggregateForTimeRange($profile, $unixAgo, $unixNow);
            $data['name'] = $profile->name;
            $data['fromDate'] = \DateTime::createFromFormat('U', $unixAgo)->format('Y-m-d');
            $data['toDate'] = \DateTime::createFromFormat('U', $unixNow)->format('Y-m-d');

            //if option is not passed send mail to each profile
            if ($this->option('accountants') === false) {
                $view = 'emails.profile.performance';
                $subject = Config::get('mail.private_mail_subject');

                MailSend::send($view, $data, $profile, $subject);
            }
            $adminAggregation[] = $data;
        }

        $overviewRecipients = Profile::where('admin', '=', true)->get();

        //if option is passed get all admins and profiles with accountant role
        if ($this->option('accountants')) {
            $overviewRecipients = Profile::where('admin', '=', true)
                ->orWhere('role', '=', 'accountant')
                ->get();
        }

        foreach ($overviewRecipients as $recipient) {
            $view = $this->option('accountants') ? 'emails.profile.salary-performance-report'
                : 'emails.profile.admin-performance-report';
            $subject = Config::get('mail.admin_performance_email_subject');

            MailSend::send($view, ['reports' => $adminAggregation], $recipient, $subject);
        }
    }
}
