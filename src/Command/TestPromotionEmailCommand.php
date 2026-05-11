<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\CommunicationPromotions;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

#[AsCommand(
    name: 'app:test-promotion-email',
    description: 'Send a test promotion email based on the last promotion in the DB',
)]
class TestPromotionEmailCommand extends Command
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly ParameterBagInterface $parameterBag,
        private readonly EntityManagerInterface $em,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('brand', InputArgument::OPTIONAL, 'comremit or sendmundo', 'comremit');
        $this->addArgument('to', InputArgument::OPTIONAL, 'Recipient email', 'alexander.afonsecac@gmail.com');
        $this->addArgument('clientId', InputArgument::OPTIONAL, 'Client ID to filter packages (auto-detects first if omitted)');
        $this->addArgument('promotionId', InputArgument::OPTIONAL, 'Promotion ID (uses last if omitted)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $brand       = strtolower($input->getArgument('brand'));
        $to          = $input->getArgument('to');
        $clientIdArg    = $input->getArgument('clientId');
        $promotionIdArg = $input->getArgument('promotionId');
        $clientId    = ($clientIdArg !== null && $clientIdArg !== '') ? (int) $clientIdArg : null;
        $promotionId = ($promotionIdArg !== null && $promotionIdArg !== '') ? (int) $promotionIdArg : null;

        $qb = $this->em->getRepository(CommunicationPromotions::class)->createQueryBuilder('p');
        if ($promotionId !== null) {
            $qb->where('p.id = :id')->setParameter('id', $promotionId);
        } else {
            $qb->orderBy('p.id', 'DESC')->setMaxResults(1);
        }
        $promotion = $qb->getQuery()->getOneOrNullResult();

        if ($promotion === null) {
            $output->writeln('<error>No promotions found in the database.</error>');
            return Command::FAILURE;
        }

        // Detect clientId from the first package if not provided
        if ($clientId === null) {
            foreach ($promotion->getProducts() as $pkg) {
                $detectedId = $pkg->getTenant()?->getClient()?->getId();
                if ($detectedId !== null) {
                    $clientId = $detectedId;
                    break;
                }
            }
        }

        if ($clientId === null) {
            $output->writeln('<error>No client found for this promotion. Pass a clientId argument.</error>');
            return Command::FAILURE;
        }

        $packages = [];
        foreach ($promotion->getProducts() as $pkg) {
            if ($pkg->getTenant()?->getClient()?->getId() === $clientId) {
                $packages[] = [
                    'name'          => $pkg->getName(),
                    'price'         => $pkg->getPriceClientPackage()?->getPrice(),
                    'priceCurrency' => $pkg->getPriceClientPackage()?->getPriceCurrency(),
                    'amount'        => $pkg->getAmount(),
                    'currency'      => $pkg->getCurrency(),
                ];
            }
        }
        usort($packages, fn($a, $b) => $a['price'] <=> $b['price']);

        $output->writeln(sprintf('<comment>Filtering packages for client #%d (%d packages found)</comment>', $clientId, count($packages)));

        $senderName = $brand === 'comremit'
            ? 'No Reply (Comremit Solutions SL)'
            : 'No Reply - (SendMundo SL)';

        $mail = (new TemplatedEmail())
            ->from(new Address($this->parameterBag->get('app.email.from'), $senderName))
            ->to(new Address($to, 'Test User'))
            ->priority(Email::PRIORITY_NORMAL)
            ->subject(sprintf('[TEST] Nueva promoción: %s', $promotion->getName()))
            ->htmlTemplate(sprintf('emails/promotions/promotion-created.%s.html.twig', $brand))
            ->context([
                'firstName'       => 'Test User',
                'promotionName'   => $promotion->getName(),
                'description'     => $promotion->getDescription(),
                'infoDescription' => $promotion->getInfoDescription(),
                'startAt'         => $promotion->getStartAt()?->format('d/m/Y H:i T'),
                'endAt'           => $promotion->getEndAt()?->format('d/m/Y H:i T'),
                'terms'           => $promotion->getTerms(),
                'packages'        => $packages,
            ]);

        $this->mailer->send($mail);

        $output->writeln(sprintf(
            '<info>Test promotion email (%s) sent to %s — Promotion #%d: "%s" (%d packages)</info>',
            $brand,
            $to,
            $promotion->getId(),
            $promotion->getName(),
            count($packages),
        ));

        return Command::SUCCESS;
    }
}
