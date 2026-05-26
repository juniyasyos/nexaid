<?php

namespace App\Filament\Panel\Resources\Users\Schemas;

use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Set;
use Illuminate\Support\Str;
use Filament\Schemas\Schema;
use Illuminate\Validation\Rules\Password;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Grid::make(12)
                    ->columnSpanFull()
                    ->schema([
                        // =========================
                        // KOLOM UTAMA (DATA INTI)
                        // =========================
                        Group::make()
                            ->columnSpanFull()
                            ->schema([
                                Section::make('Identitas Pengguna')
                                    ->description('Data utama yang digunakan untuk identifikasi dan login.')
                                    ->schema([
                                        Grid::make(2)
                                            ->schema([
                                                TextInput::make('name')
                                                    ->label('Nama Lengkap')
                                                    ->required()
                                                    ->maxLength(255)
                                                    ->placeholder('John Doe')
                                                    ->autocapitalize('words')
                                                    ->autocomplete('name')
                                                    ->helperText('Nama lengkap sesuai identitas resmi.')
                                                    ->prefixIcon('heroicon-m-user'),

                                                TextInput::make('nip')
                                                    ->label('NIP')
                                                    ->required()
                                                    ->unique(
                                                        table: User::class,
                                                        column: 'nip',
                                                        ignoreRecord: true,
                                                    )
                                                    ->placeholder('199101012020031001')
                                                    ->autocomplete('username')
                                                    ->helperText('Nomor Induk Pegawai sebagai identitas login.')
                                                    ->prefixIcon('heroicon-m-identification'),
                                            ]),

                                        Grid::make(1)
                                            ->schema([
                                                TextInput::make('email')
                                                    ->email()
                                                    ->label('Email')
                                                    ->nullable()
                                                    ->columnSpanFull()
                                                    ->unique(
                                                        table: User::class,
                                                        column: 'email',
                                                        ignoreRecord: true,
                                                    )
                                                    ->placeholder('email@domain.com')
                                                    ->helperText('Email opsional untuk notifikasi dan pemulihan akun.')
                                                    ->prefixIcon('heroicon-m-envelope'),
                                            ]),
                                    ]),

                                Section::make('Informasi Pribadi')
                                    ->collapsible()
                                    ->collapsed()
                                    ->description('Detail tambahan terkait identitas pengguna.')
                                    ->schema([
                                        Grid::make(2)
                                            ->schema([
                                                TextInput::make('place_of_birth')
                                                    ->label('Tempat Lahir')
                                                    ->nullable()
                                                    ->placeholder('Jakarta')
                                                    ->helperText('Kota atau kabupaten tempat lahir.')
                                                    ->prefixIcon('heroicon-m-map-pin'),

                                                DatePicker::make('date_of_birth')
                                                    ->label('Tanggal Lahir')
                                                    ->nullable()
                                                    ->placeholder('Pilih tanggal lahir')
                                                    ->helperText('Tanggal lahir pengguna.'),

                                                Select::make('gender')
                                                    ->label('Jenis Kelamin')
                                                    ->options([
                                                        'male' => '🧑Laki-laki',
                                                        'female' => '👩Perempuan',
                                                    ])
                                                    ->nullable()
                                                    ->prefixIcon('heroicon-m-user')
                                                    ->placeholder('Pilih jenis kelamin')
                                                    ->helperText('Jenis kelamin untuk data demografis.'),

                                                TextInput::make('phone_number')
                                                    ->label('No. HP')
                                                    ->tel()
                                                    ->nullable()
                                                    ->placeholder('+62 812 3456 7890')
                                                    ->helperText('Nomor telepon yang dapat dihubungi.')
                                                    ->prefixIcon('heroicon-m-phone'),
                                            ]),

                                        Textarea::make('address_ktp')
                                            ->label('Alamat KTP')
                                            ->rows(3)
                                            ->columnSpanFull()
                                            ->placeholder('Jalan, Kecamatan, Kota, Provinsi')
                                            ->helperText('Alamat lengkap sesuai KTP untuk verifikasi identitas.'),
                                    ]),

                                Section::make('Keamanan Akun')
                                    ->description('Pengaturan kredensial dan status akun.')
                                    ->schema([
                                        Grid::make(2)
                                            ->schema([
                                                TextInput::make('password')
                                                    ->label('Password')
                                                    ->password()
                                                    ->revealable()
                                                    ->rule(Password::default())
                                                    ->required(fn(string $operation) => $operation === 'create')
                                                    ->dehydrated(fn($state) => filled($state))
                                                    ->placeholder(
                                                        fn($operation) =>
                                                        $operation === 'create'
                                                            ? 'Minimal 8 karakter'
                                                            : 'Kosongkan jika tidak diubah'
                                                    )
                                                    ->default('rschjaya1234')
                                                    ->helperText('Kosongkan saat edit jika tidak ingin mengganti password.')
                                                    ->prefixIcon('heroicon-m-key')
                                                    ->suffixAction(
                                                        Action::make('generatePassword')
                                                            ->icon('heroicon-m-sparkles')
                                                            ->tooltip('Generate password random')
                                                            ->action(function (Set $set): void {
                                                                $set('password', Str::random(12));
                                                            })
                                                    ),

                                                Select::make('status')
                                                    ->label('Status')
                                                    ->prefixIcon('heroicon-m-flag')
                                                    ->options([
                                                        'active' => 'Active',
                                                        'inactive' => 'Inactive',
                                                        'suspended' => 'Suspended',
                                                    ])
                                                    ->default('active')
                                                    ->required()
                                                    ->helperText('Pilih status akun untuk klasifikasi penggunaan.'),
                                            ]),
                                    ]),
                            ]),
                    ]),

                // =========================
                // SIDEBAR (DATA SEKUNDER)
                // =========================
                Group::make()
                    ->columnSpan(4)
                    ->schema([
                        Section::make('Foto & Media')
                            ->columnSpanFull()
                            ->description('Unggah avatar dan tanda tangan digital pengguna.')
                            ->schema([
                                TextInput::make('avatar_url')
                                    ->label('Avatar URL'),
                                TextInput::make('ttd_url')
                                    ->label('Tanda Tangan URL'),
                                Grid::make(2)
                                    ->schema([
                                        FileUpload::make('avatar_url')
                                            ->label('Avatar Pengguna')
                                            ->disk(\App\Support\StorageFallback::isS3Available() ? 's3' : 'public')
                                            ->directory('avatars')
                                            ->image()
                                            ->imageEditor()
                                            ->openable()
                                            ->acceptedFileTypes(['image/png', 'image/jpeg', 'image/jpg'])
                                            ->maxSize(2048)
                                            ->helperText('Unggah foto profil pengguna. Gunakan format PNG/JPG.'),

                                        FileUpload::make('ttd_url')
                                            ->label('Tanda Tangan')
                                            ->disk(\App\Support\StorageFallback::isS3Available() ? 's3' : 'public')
                                            ->directory('ttd')
                                            ->image()
                                            ->openable()
                                            ->acceptedFileTypes(['image/png', 'image/jpeg', 'image/jpg'])
                                            ->maxSize(2048)
                                            ->helperText('Unggah gambar tanda tangan berformat PNG atau JPG.'),
                                    ])
                            ]),

                        Section::make('Informasi Sistem')
                            ->hidden(fn($operation) => $operation === 'create')
                            ->schema([
                                TextInput::make('created_at')
                                    ->label('Dibuat')
                                    ->disabled()
                                    ->visible(fn($operation) => $operation === 'edit'),

                                TextInput::make('updated_at')
                                    ->label('Diupdate')
                                    ->disabled()
                                    ->visible(fn($operation) => $operation === 'edit'),
                            ]),
                    ]),
            ]);
    }
}
