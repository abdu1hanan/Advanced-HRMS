<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SalarySlipMail extends Mailable
{
    use Queueable, SerializesModels;

    public User $user;
    public string $monthName;
    public string $year;
    public string $pdfContent;
    public bool $includeSalarySlip;
    public bool $includeTracksheet;

    public function __construct(User $user, string $monthName, string $year, string $pdfContent, bool $includeSalarySlip = true, bool $includeTracksheet = true)
    {
        $this->user = $user;
        $this->monthName = $monthName;
        $this->year = $year;
        $this->pdfContent = $pdfContent;
        $this->includeSalarySlip = $includeSalarySlip;
        $this->includeTracksheet = $includeTracksheet;
    }

    public function envelope(): Envelope
    {
        $ccAddresses = [];
        $rawCc = config('services.salary_slip.cc_emails');

        if (!empty($rawCc)) {
            $emails = array_map('trim', explode(',', $rawCc));
            foreach ($emails as $email) {
                if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $ccAddresses[] = new Address($email);
                }
            }
        }

        if ($this->includeSalarySlip && $this->includeTracksheet) {
            $subject = "Salary Slip and Hours Summary for {$this->monthName} {$this->year}";
        } elseif ($this->includeSalarySlip) {
            $subject = "Salary Slip for {$this->monthName} {$this->year}";
        } else {
            $subject = "Monthly Track Sheet and Hours Summary for {$this->monthName} {$this->year}";
        }

        return new Envelope(
            subject: $subject,
            cc: $ccAddresses,
        );
    }

    public function content(): Content
    {
        return new Content(
            html: 'emails.salary-slip',
            with: [
                'userName' => $this->user->name,
                'monthName' => $this->monthName,
                'year' => $this->year,
            ]
        );
    }

    public function attachments(): array
    {
        $cleanName = str_replace(' ', '_', $this->user->name);
        if ($this->includeSalarySlip && $this->includeTracksheet) {
            $filename = "Salary_Slip_and_Track_Sheet_{$cleanName}_{$this->monthName}_{$this->year}.pdf";
        } elseif ($this->includeSalarySlip) {
            $filename = "Salary_Slip_{$cleanName}_{$this->monthName}_{$this->year}.pdf";
        } else {
            $filename = "Track_Sheet_{$cleanName}_{$this->monthName}_{$this->year}.pdf";
        }

        return [
            Attachment::fromData(fn () => $this->pdfContent, $filename)
                ->withMime('application/pdf'),
        ];
    }
}
