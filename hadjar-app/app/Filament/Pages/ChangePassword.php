<?php

namespace App\Filament\Pages;

use App\Models\User;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Components\Attributes\Exposed;
use Filament\Support\Components\Attributes\Unexposed;

/**
 * @property-read Schema $form
 */
class ChangePassword extends Page
{
    protected static ?string $slug = 'change-password';

    protected static ?string $title = 'Ganti Kata Sandi';

    protected static bool $shouldRegisterNavigation = false;

    /**
     * @var array<string, mixed> | null
     */
    public ?array $data = [];

    #[Unexposed]
    public function mount(): void
    {
        $this->form->fill();
    }

    #[Unexposed]
    public function defaultForm(Schema $schema): Schema
    {
        return $schema
            ->statePath('data');
    }

    #[Unexposed]
    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Wajib Ganti Kata Sandi')
                    ->description('Anda wajib mengganti kata sandi sementara sebelum dapat mengakses halaman lain.')
                    ->schema([
                        TextInput::make('password')
                            ->label('Kata Sandi Baru')
                            ->password()
                            ->revealable()
                            ->required()
                            ->minLength(8)
                            ->confirmed(),
                        TextInput::make('password_confirmation')
                            ->label('Konfirmasi Kata Sandi Baru')
                            ->password()
                            ->revealable()
                            ->required()
                            ->minLength(8),
                    ]),
            ]);
    }

    #[Unexposed]
    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                Form::make([EmbeddedSchema::make('form')])
                    ->id('form')
                    ->livewireSubmitHandler('save')
                    ->footer([
                        Actions::make([
                            Action::make('save')
                                ->label('Simpan Kata Sandi')
                                ->submit('save'),
                        ]),
                    ]),
            ]);
    }

    #[Exposed]
    public function save(): void
    {
        $data = $this->form->getState();

        /** @var ?User $user */
        $user = Filament::auth()->user();

        if (! $user) {
            return;
        }

        $user->update([
            'password' => $data['password'],
            'must_change_password' => false,
        ]);

        session()->put([
            'password_hash_' . Filament::getAuthGuard() => $user->getAuthPassword(),
        ]);
        Filament::auth()->setUser($user);

        Notification::make()
            ->title('Kata sandi berhasil diperbarui.')
            ->success()
            ->send();

        $this->redirect(Filament::getUrl() ?? '/panel');
    }
}
