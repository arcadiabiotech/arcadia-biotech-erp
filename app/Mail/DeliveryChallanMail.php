<?php

namespace App\Mail;

use App\Models\Dispatch;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * "Email PDF" button on the dispatch show page. Renders the exact same
 * dispatches.print Blade used for Print/Download so the attachment always
 * matches what staff see on screen — one template, three delivery paths
 * (browser print, direct download, email attachment).
 */
class DeliveryChallanMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Dispatch $dispatch) {}

    public function envelope(): Envelope
    {
        $dcNo = $this->dispatch->challan_no ?? $this->dispatch->dispatch_no;

        return new Envelope(
            subject: "Delivery Challan {$dcNo} — ".config('company.name'),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.delivery-challan',
            with: ['dispatch' => $this->dispatch],
        );
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        $dispatch = $this->dispatch;
        $filename = ($dispatch->challan_no ?? $dispatch->dispatch_no).'.pdf';

        return [
            Attachment::fromData(
                fn () => Pdf::loadView('dispatches.print', ['dispatch' => $dispatch])->output(),
                $filename,
            )->withMime('application/pdf'),
        ];
    }
}
