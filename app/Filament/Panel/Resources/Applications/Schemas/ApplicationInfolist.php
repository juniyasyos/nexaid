<?php

namespace App\Filament\Panel\Resources\Applications\Schemas;

use App\Domain\Iam\Models\Application;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ApplicationInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Group::make()
                    ->columnSpanFull()
                    ->schema([
                        // ====================================
                        // IDENTITAS APLIKASI
                        // ====================================
                        Section::make('Profil Aplikasi')
                            ->icon('heroicon-m-cube')
                            ->description('Informasi identitas utama aplikasi yang terdaftar di IAM.')
                            ->schema([
                                TextEntry::make('name')
                                    ->label('Nama Aplikasi')
                                    ->weight('semibold')
                                    ->icon('heroicon-m-rectangle-stack')
                                    ->extraAttributes([
                                        'class' => 'text-base text-gray-900 dark:text-gray-100',
                                    ]),

                                IconEntry::make('enabled')
                                    ->label('Status Aplikasi')
                                    ->boolean()
                                    ->trueIcon('heroicon-m-check-badge')
                                    ->falseIcon('heroicon-m-no-symbol')
                                    ->trueColor('success')
                                    ->falseColor('danger'),

                                TextEntry::make('app_key')
                                    ->label('App Key')
                                    ->badge()
                                    ->color('gray')
                                    ->copyable()
                                    ->copyMessage('App key disalin.')
                                    ->helperText('Digunakan sebagai identifier unik di sistem IAM.')
                                    ->hintIcon('heroicon-m-key'),
                            ])
                            ->columns(2),

                        Section::make('Deskripsi & Tampilan')
                            ->icon('heroicon-m-document-text')
                            ->description('Deskripsi singkat dan logo aplikasi untuk antarmuka login SSO.')
                            ->schema([
                                TextEntry::make('description')
                                    ->label('Deskripsi')
                                    ->placeholder('Belum ada deskripsi.')
                                    ->columnSpanFull(),

                                TextEntry::make('logo_url')
                                    ->label('Logo URL')
                                    ->placeholder('-')
                                    ->copyable()
                                    ->copyMessage('URL logo disalin.')
                                    ->columnSpanFull(),
                            ])
                            ->columns(2),

                        // ====================================
                        // KONFIGURASI SSO & TOKEN
                        // ====================================
                        Section::make('Konfigurasi SSO & Token')
                            ->icon('heroicon-m-shield-check')
                            ->description('Pengaturan integrasi SSO, callback, dan masa berlaku token.')
                            ->schema([
                                TextEntry::make('callback_url')
                                    ->label('Callback URL')
                                    ->placeholder('-')
                                    ->copyable()
                                    ->copyMessage('Callback URL disalin.')
                                    ->helperText('Endpoint yang akan dipanggil setelah proses otentikasi SSO.'),

                                TextEntry::make('redirect_uris')
                                    ->label('Redirect URIs')
                                    ->state(fn(Application $record) => $record->redirect_uris ?? [])
                                    ->formatStateUsing(function ($state): ?string {
                                        if (empty($state)) {
                                            return null;
                                        }

                                        return collect($state)->implode(' • ');
                                    })
                                    ->placeholder('Belum ada redirect URI yang terdaftar.')
                                    ->helperText('Daftar URI yang diizinkan sebagai tujuan redirect setelah SSO berhasil.')
                                    ->columnSpanFull(),

                                TextEntry::make('token_expiry')
                                    ->label('Masa Berlaku Token')
                                    ->state(fn(Application $record) => $record->getTokenExpirySeconds())
                                    ->formatStateUsing(function (?int $seconds): string {
                                        $seconds ??= 3600;

                                        $minutes = intdiv($seconds, 60);
                                        $hours = round($seconds / 3600, 2);

                                        return "{$seconds} detik ({$minutes} menit ≈ {$hours} jam)";
                                    })
                                    ->badge()
                                    ->color('primary')
                                    ->helperText('Durasi maksimal akses token sebelum kedaluwarsa.'),

                                TextEntry::make('secret')
                                    ->label('SSO Secret (hashed)')
                                    ->state(fn(Application $record) => $record->secret)
                                    ->formatStateUsing(function (?string $state): ?string {
                                        if (blank($state)) {
                                            return null;
                                        }

                                        // Tampilkan sebagian saja demi keamanan
                                        return 'sha256:' . substr($state, 0, 10) . '…';
                                    })
                                    ->placeholder('-')
                                    ->copyable()
                                    ->copyMessage('Nilai hash secret disalin.')
                                    ->helperText('Disimpan dalam bentuk hash. Nilai asli hanya diketahui saat awal pembuatan.'),
                            ])
                            ->columns(2),

                        // ====================================
                        // RELASI & METADATA
                        // ====================================
                        Section::make('Relasi & Metadata')
                            ->icon('heroicon-m-information-circle')
                            ->description('Informasi pembuat aplikasi, jumlah role, dan riwayat perubahan.')
                            ->schema([
                                TextEntry::make('creator.name')
                                    ->label('Dibuat oleh')
                                    ->placeholder('-')
                                    ->icon('heroicon-m-user-circle'),

                                TextEntry::make('creator.email')
                                    ->label('Email Pembuat')
                                    ->placeholder('-')
                                    ->icon('heroicon-m-envelope'),

                                TextEntry::make('roles_count')
                                    ->label('Jumlah Role')
                                    ->numeric()
                                    ->badge()
                                    ->color('info')
                                    ->helperText('Total role yang didefinisikan untuk aplikasi ini.'),

                                TextEntry::make('system_roles_count')
                                    ->label('System Role')
                                    ->numeric()
                                    ->badge()
                                    ->color('warning')
                                    ->helperText('Role khusus yang ditandai sebagai system role.'),

                                Section::make()
                                    ->columnSpan(2)
                                    ->schema([
                                        TextEntry::make('created_at')
                                            ->label('Dibuat pada')
                                            ->dateTime()
                                            ->placeholder('-')
                                            ->color('gray')
                                            ->icon('heroicon-m-calendar'),

                                        TextEntry::make('updated_at')
                                            ->label('Terakhir diperbarui')
                                            ->dateTime()
                                            ->placeholder('-')
                                            ->color('gray')
                                            ->icon('heroicon-m-clock'),

                                        TextEntry::make('deleted_at')
                                            ->label('Dihapus pada')
                                            ->dateTime()
                                            ->placeholder('-')
                                            ->visible(fn(Application $record): bool => $record->trashed())
                                            ->color('danger')
                                            ->icon('heroicon-m-trash'),
                                    ])
                            ])
                            ->columns(3),
                    ]),
            ]);
    }
}
