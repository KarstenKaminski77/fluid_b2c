<?php

namespace App\Command;

use App\Services\ForceOrderShipped;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:force-order-shipped',
    description: 'Add a short description for your command',
)]
class ForceOrderShippedCommand extends Command
{
    private $forceOrderShipped;

    public function __construct(ForceOrderShipped $forceOrderShipped)
    {
        $this->forceOrderShipped = $forceOrderShipped;
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('orderId', InputArgument::REQUIRED, 'Order ID?')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $orderId = $input->getArgument('orderId');

        if ($orderId) {
            $io->note(sprintf('You passed an order id: %s', $orderId));
        }

        $this->forceOrderShipped->forceOrderShipped($orderId);

        $io->success('Order status updated!.');

        return Command::SUCCESS;
    }
}
