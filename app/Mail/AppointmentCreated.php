<?php

namespace App\Mail;

use App\Models\Appointment;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class AppointmentCreated extends Mailable
{
    use Queueable, SerializesModels;

    public $appointment;
    public $recipientType; // 'patient' ou 'medecin'
    public $patient;
    public $medecin;
    public $autoConfirmed; // Nouveau: pour indiquer si c'est confirmé automatiquement

    public function __construct(Appointment $appointment, $recipientType, $autoConfirmed = false)
    {
        $this->appointment = $appointment;
        $this->recipientType = $recipientType;
        $this->patient = $appointment->patient;
        $this->medecin = $appointment->medecin;
        $this->autoConfirmed = $autoConfirmed; // Nouveau paramètre
    }

    public function build()
    {
        // Adaptation du sujet en fonction de la confirmation automatique
        if ($this->autoConfirmed) {
            $subject = $this->recipientType === 'patient'
                ? 'Votre rendez-vous a été confirmé'
                : 'Nouveau rendez-vous confirmé';
        } else {
            $subject = $this->recipientType === 'patient'
                ? 'Votre demande de rendez-vous a été enregistrée'
                : 'Nouveau rendez-vous reçu';
        }

        return $this->subject($subject)
            ->view('emails.appointment_created')
            ->with([
                'appointment' => $this->appointment,
                'recipientType' => $this->recipientType,
                'patient' => $this->appointment->patient,
                'medecin' => $this->appointment->medecin,
                'autoConfirmed' => $this->autoConfirmed, // Nouveau: passer à la vue
            ]);
    }
}