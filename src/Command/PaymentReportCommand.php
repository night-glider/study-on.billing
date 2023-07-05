<?php

namespace App\Command;

use App\Enum\CourseEnum;
use App\Repository\TransactionRepository;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use App\Service\TwigService;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

class PaymentReportCommand extends Command
{
    protected static $defaultName = 'payment:report';
    private TwigService $twig;
    private MailerInterface $mailer;
    private TransactionRepository $transactionRepository;

    public function __construct(TwigService $twig, MailerInterface $mailer, TransactionRepository $transactionRepository)
    {
        $this->twig = $twig;
        $this->mailer = $mailer;
        $this->transactionRepository = $transactionRepository;
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $format = 'Y-m-d H:i:s';
        $monthStart = \DateTime::createFromFormat($format, date('Y-m-01 00:00:00'));
        $monthEnd   = \DateTime::createFromFormat($format, date('Y-m-t 23:59:59'));
        $courses = $this->transactionRepository->periodReport($monthStart, $monthEnd);
        $coursesByEmail = [];
        foreach ($courses as $el) {
            $el['type'] = CourseEnum::NAMES[$el['type']];
            if (isset($coursesByEmail[$el['email']])) {
                $coursesByEmail[$el['email']][] = $el;
            } else {
                $coursesByEmail[$el['email']] = [$el];
            }
        }

        foreach ($coursesByEmail as $email => $userCourses) {
            $totalPaid = 0.0;
            foreach ($userCourses as $userCourse) {
                $totalPaid += $userCourse['common_price'];
            }

            $html = $this->twig->render(
                'email/payment_report.html.twig',
                [
                    'period' => ['from' => $monthStart, 'to' => $monthEnd],
                    'courses' => $userCourses,
                    'total_paid' => $totalPaid,
                ]
            );

            $email = (new Email())
                ->from("admins@studyon.com")
                ->to($email)
                ->subject('Отчет об операциях за месяц')
                ->html($html);

            try {
                $this->mailer->send($email);
            } catch (TransportExceptionInterface $e) {
                return Command::FAILURE;
            }
        }

        return Command::SUCCESS;
    }
}
