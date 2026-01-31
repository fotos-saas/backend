<?php

namespace App\Livewire;

use App\Helpers\SmsHelper;
use App\Models\TabloContact;
use App\Models\TabloProject;
use Filament\Notifications\Notification;
use Livewire\Component;

class OutreachContactsModal extends Component
{
    public ?int $projectId = null;

    public ?int $editingContactId = null;

    public string $editingNote = '';

    public function mount(int $projectId): void
    {
        $this->projectId = $projectId;
    }

    public function registerCall(int $contactId): void
    {
        $contact = TabloContact::find($contactId);
        if ($contact) {
            $contact->registerCall();

            Notification::make()
                ->title('Hívás regisztrálva')
                ->body($contact->name . ' - ' . $contact->call_count . '. hívás')
                ->success()
                ->send();

            // Dispatch browser event to open tel: link
            $phone = preg_replace('/[^\d+]/', '', $contact->phone);
            $this->dispatch('open-phone-link', url: 'tel:' . $phone);
        }
    }

    public function registerSms(int $contactId): void
    {
        $contact = TabloContact::find($contactId);
        if ($contact) {
            $contact->registerSms();

            $project = $contact->project;
            $smsUrl = $this->buildSmsUrl($contact, $project);

            Notification::make()
                ->title('SMS regisztrálva')
                ->body($contact->name . ' - ' . $contact->sms_count . '. SMS')
                ->success()
                ->send();

            // Dispatch browser event to open sms: link
            $this->dispatch('open-phone-link', url: $smsUrl);
        }
    }

    public function startEditNote(int $contactId): void
    {
        $contact = TabloContact::find($contactId);
        if ($contact) {
            $this->editingContactId = $contactId;
            $this->editingNote = $contact->note ?? '';
        }
    }

    public function cancelEditNote(): void
    {
        $this->editingContactId = null;
        $this->editingNote = '';
    }

    public function saveNote(): void
    {
        if ($this->editingContactId) {
            $contact = TabloContact::find($this->editingContactId);
            if ($contact) {
                $contact->update(['note' => $this->editingNote]);

                Notification::make()
                    ->title('Megjegyzés mentve')
                    ->success()
                    ->send();
            }
        }

        $this->editingContactId = null;
        $this->editingNote = '';
    }

    protected function buildSmsUrl(TabloContact $contact, TabloProject $project): string
    {
        return SmsHelper::generateSmsLinkForContact($contact, $project);
    }

    public function render()
    {
        $project = TabloProject::with(['contacts', 'school'])->find($this->projectId);

        return view('livewire.outreach-contacts-modal', [
            'project' => $project,
            'contacts' => $project?->contacts ?? collect(),
        ]);
    }
}
