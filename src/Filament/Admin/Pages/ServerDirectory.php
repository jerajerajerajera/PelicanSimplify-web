<?php
namespace Deckzwabbers\ServerDirectory\Filament\Admin\Pages;

use App\Enums\TablerIcon;
use App\Filament\Admin\Resources\Servers\ServerResource;
use App\Models\Server;
use BackedEnum;
use Deckzwabbers\ServerDirectory\Services\DirectoryStore;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;

final class ServerDirectory extends Page implements HasTable, HasForms
{
    use InteractsWithTable, InteractsWithForms;

    protected static string|BackedEnum|null $navigationIcon = TablerIcon::World;
    protected static ?string $navigationLabel = 'Server Directory';
    protected static ?string $title = 'Server Directory';
    protected static ?string $slug = 'server-directory';
    protected string $view = 'server-directory::filament.admin.directory';

    public static function canAccess(): bool { return ServerResource::canViewAny(); }

    public function table(Table $table): Table
    {
        return $table->query(ServerResource::getEloquentQuery()->with(['allocation', 'node', 'egg']))
            ->columns([
                TextColumn::make('name')->label('Server')->searchable()->sortable()
                    ->description(fn (Server $record) => DirectoryStore::metadata($record)['server_alias'] ?: null),
                TextColumn::make('egg.name')->label('Server type'),
                ToggleColumn::make('website_visible')->label('Show on website')
                    ->getStateUsing(fn (Server $record) => DirectoryStore::metadata($record)['visible'])
                    ->disabled(fn (Server $record) => !ServerResource::canEdit($record))
                    ->updateStateUsing(function (Server $record, bool $state): bool {
                        abort_unless(ServerResource::canEdit($record), 403);
                        DirectoryStore::save($record, [...DirectoryStore::metadata($record), 'visible' => $state]);
                        return $state;
                    }),
                TextColumn::make('published_address')->label('Website address')
                    ->getStateUsing(fn (Server $record) => DirectoryStore::metadata($record)['effective_address'] ?: 'Set an address'),
                TextColumn::make('listing_source')->label('Settings source')->badge()
                    ->getStateUsing(fn (Server $record) => DirectoryStore::metadata($record)['source']),
            ])
            ->recordActions([
                Action::make('edit_listing')->label('Edit listing')->icon('tabler-pencil')
                    ->authorize(fn (Server $record) => ServerResource::canEdit($record))
                    ->slideOver()->modalHeading('Website listing')
                    ->fillForm(fn (Server $record) => DirectoryStore::metadata($record))
                    ->schema([
                        Toggle::make('visible')->label('Show on website'),
                        TextInput::make('display_name')->label('Server name')->disabled()->dehydrated(false)->helperText('Uses the server name. Rename the server in its Settings.'),
                        Textarea::make('description')->label('Description')->maxLength(1200)->rows(3),
                        Select::make('game')->label('Game')->options(['minecraft' => 'Minecraft', 'other' => 'Other game'])->required(),
                        TextInput::make('type')->label('Type / game name')->required()->maxLength(100),
                        Select::make('server_flavour')->label('Minecraft category')->options(['vanilla'=>'Vanilla','plus'=>'Vanilla+','modded'=>'Modpack'])->required(),
                        Textarea::make('plugins')->label('Installed plugin files (from Wings)')->disabled()->dehydrated(false)->rows(4)->helperText('Retrieved automatically from /plugins every minute. Jar filenames show installed files, not whether each plugin is enabled.'),
                        TextInput::make('version')->label('Minecraft / game version')->maxLength(100)->helperText('Optional. Minecraft status supplies the version when this is empty. This is separate from the modpack version.'),
                        Select::make('background_id')->label('Minecraft screenshot / background')->options(['auto'=>'Automatic: keep assigned image','valley'=>'Green mountain valley · valley','harbor'=>'Pirate harbor · harbor','village'=>'Village at dusk · village','survival'=>'Survival landscape · survival','town'=>'Medieval town · town'])->required()->helperText('Automatic assigns an unused image and saves its ID. Hidden servers keep their image; deleted servers free it. A custom screenshot URL below overrides this selection.'),
                        TextInput::make('screenshot_url')->label('Game screenshot URL')->url()->maxLength(2048)
                            ->helperText('Optional override for the game background. Minecraft and Counter-Strike have built-in game images; a direct https:// image URL overrides these.'),
                        TextInput::make('icon_url')->label('Server icon URL (optional)')->url()->maxLength(2048)
                            ->helperText('Leave empty to use the live Minecraft server-icon. You can override it with a direct image URL, also shown while offline.'),
                        Textarea::make('motd')->label('MOTD override (optional)')->maxLength(500)->rows(2)
                            ->helperText('Leave empty to use the live Minecraft MOTD. An override also remains visible while offline. This changes the website listing, not server.properties.'),
                        Section::make('Modpack showcase')->schema([
                            TextInput::make('modpack_name')->label('Modpack name')->maxLength(150),
                            TextInput::make('modpack_version')->label('Modpack version')->maxLength(100),
                            TextInput::make('modpack_icon_url')->label('Modpack icon URL')->url()->maxLength(2048),
                            Textarea::make('modpack_description')->label('About this modpack')->maxLength(1200)->rows(3),
                            TextInput::make('modpack_url')->label('Modpack website')->url()->maxLength(2048),
                            TextInput::make('client_url')->label('Client version download')->url()->maxLength(2048)
                                ->helperText('Link to the exact client release installed on this server, not the server pack. Works with Modrinth, CurseForge, FTB, Technic and other providers.'),
                        ])->description('Optional overrides. Modrinth and FTB packs are detected from the installed egg variables. Name, icon, description and installed release are retrieved automatically where available. Other providers can be filled in here.'),
                        TextInput::make('bedrock_address')->label('Bedrock join address (optional)')->maxLength(255)
                            ->helperText('Only for servers that accept Bedrock players. Format: host:19132. Adds an Open in Minecraft button. Java modpacks use the client download and join instructions instead.'),
                        TextInput::make('published_address')->label('Join address')->maxLength(255)
                            ->helperText('Shown and copied on the website. Empty uses the connected subdomain, then the game address with this server\'s port. Private and Tailscale addresses are never shown to visitors.'),
                        TextInput::make('requested_subdomain')->label('Subdomain')->disabled()->dehydrated(false)
                            ->helperText(fn (Server $record) => DirectoryStore::metadata($record)['subdomain_address'] !== ''
                                ? 'Connected by Simplify. Used as the join address while the field above is empty.'
                                : 'Set by the server owner in Simplify (Subdomain in the server menu). Simplify connects it in Cloudflare.'),
                        TextInput::make('sort_order')->label('Display order')->integer()->minValue(0)->maxValue(9999)->required(),
                        Section::make('Status connection')->schema([
                            TextInput::make('target_host')->label('Minecraft status host')->required()->maxLength(253),
                            TextInput::make('target_port')->label('Status port')->integer()->minValue(1)->maxValue(65535)->required(),
                        ])->description('Internal connection used by the website service. Hidden from visitors. Other games show Unknown until their status adapter is added.'),
                    ])
                    ->action(function (Server $record, array $data): void {
                        abort_unless(ServerResource::canEdit($record), 403);
                        DirectoryStore::save($record, $data);
                        Notification::make()->title('Website listing saved')->body('The website refreshes within approximately one minute.')->success()->send();
                    }),
            ]);
    }
}
