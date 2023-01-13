<?php

namespace App\Command;

use App\Services\UpdateInventory;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:update-inventory',
    description: 'Add a short description for your command',
)]
class UpdateInventoryCommand extends Command
{
    private $updateInventory;

    public function __construct(UpdateInventory $updateInventory)
    {
        $this->updateInventory = $updateInventory;
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('distributorId', InputArgument::REQUIRED, 'Distributor ID');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $distributorId = $input->getArgument('distributorId');

        if ($distributorId) {
            $io->note(sprintf('You passed an argument: %s', $distributorId));
        }

        $this->updateInventory->updateInventory($distributorId);

        $io->success('Command Successfully Executed.');

        return Command::SUCCESS;
    }
}
