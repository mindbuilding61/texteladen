<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\IncomingInvoice;
use App\Entity\OutgoingInvoice;
use App\Repository\IncomingInvoiceRepository;
use App\Repository\OutgoingInvoiceRepository;
use App\Repository\SettingsRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class DashboardController extends AbstractController
{
    #[Route('/', name: 'dashboard', methods: ['GET'])]
    public function index(
        IncomingInvoiceRepository $incoming,
        OutgoingInvoiceRepository $outgoing,
        SettingsRepository $settings,
    ): Response {
        $settings->getOrCreate();

        $recentIncoming = $incoming->findBy([], ['receivedAt' => 'DESC'], 10);
        $recentOutgoing = $outgoing->findBy([], ['issueDate' => 'DESC', 'id' => 'DESC'], 10);

        $unpaidIncoming = $incoming->count(['status' => IncomingInvoice::STATUS_NEW])
            + $incoming->count(['status' => IncomingInvoice::STATUS_REVIEWED]);
        $openOutgoing = $outgoing->count(['status' => OutgoingInvoice::STATUS_ISSUED])
            + $outgoing->count(['status' => OutgoingInvoice::STATUS_SENT]);

        return $this->render('dashboard/index.html.twig', [
            'recentIncoming' => $recentIncoming,
            'recentOutgoing' => $recentOutgoing,
            'unpaidIncoming' => $unpaidIncoming,
            'openOutgoing' => $openOutgoing,
            'settings' => $settings->getOrCreate(),
        ]);
    }
}
