<?php

namespace App\Filament\Resources\EventResource\Widgets;

use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use App\Models\User;
use App\Models\Event;
use App\Models\EventRegistration;
use Illuminate\Database\Eloquent\Builder;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\DB;

class EventRegisteredUsersWidget extends BaseWidget
{
    protected static ?string $heading = 'Registered Users';
    
    public $record = null;
    
    protected int | string | array $columnSpan = 'full';
    
    protected static bool $isCollapsible = true;
    
    public function table(Table $table): Table
    {
        return $table
            ->query(function () {
                if (!$this->record) {
                    return User::query()->whereNull('id');
                }
                
                $eventUuid = $this->record->uuid;
                
                return User::query()
                    ->join('event_registrations', 'users.uuid', '=', 'event_registrations.user_uuid')
                    ->where('event_registrations.event_uuid', $eventUuid)
                    ->select('users.*', 'event_registrations.status', 'event_registrations.id as registration_id')
                    ->orderBy('users.user_name');
            })
            ->columns([
                Tables\Columns\TextColumn::make('user_name')
                    ->label('Username')
                    ->searchable(),
                Tables\Columns\TextColumn::make('email')
                    ->label('Email')
                    ->searchable(),
                Tables\Columns\TextColumn::make('country')
                    ->label('Country')
                    ->searchable(),
                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'registered' => 'primary',
                        'confirmed' => 'success',
                        'pending' => 'warning',
                        'rejected' => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Registered Date')
                    ->dateTime(),
            ])
            ->actions([
                Tables\Actions\Action::make('remove_user')
                    ->label('Remove')
                    ->color('danger')
                    ->icon('heroicon-o-trash')
                    ->requiresConfirmation()
                    ->modalHeading('Remove User from Event')
                    ->modalDescription('Are you sure you want to remove this user from the event? This action cannot be undone.')
                    ->action(function ($record) {
                        try {
                            // Find the registration and delete it
                            EventRegistration::where('event_uuid', $this->record->uuid)
                                ->where('user_uuid', $record->uuid)
                                ->delete();
                                
                            Notification::make()
                                ->title('User removed successfully')
                                ->success()
                                ->send();
                                
                        } catch (\Exception $e) {
                            Notification::make()
                                ->title('Error removing user')
                                ->body('An error occurred: ' . $e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'registered' => 'Registered',
                        'confirmed' => 'Confirmed',
                        'pending' => 'Pending',
                        'rejected' => 'Rejected',
                    ]),
            ])
            ->bulkActions([
                Tables\Actions\BulkAction::make('remove_users')
                    ->label('Remove Selected')
                    ->color('danger')
                    ->icon('heroicon-o-trash')
                    ->requiresConfirmation()
                    ->modalHeading('Remove Selected Users')
                    ->modalDescription('Are you sure you want to remove these users from the event? This action cannot be undone.')
                    ->action(function ($records) {
                        $count = 0;
                        
                        DB::beginTransaction();
                        try {
                            foreach ($records as $record) {
                                EventRegistration::where('event_uuid', $this->record->uuid)
                                    ->where('user_uuid', $record->uuid)
                                    ->delete();
                                $count++;
                            }
                            
                            DB::commit();
                            
                            Notification::make()
                                ->title("{$count} users removed successfully")
                                ->success()
                                ->send();
                                
                            // Refresh the table
                            $this->refresh();
                        } catch (\Exception $e) {
                            DB::rollBack();
                            
                            Notification::make()
                                ->title('Error removing users')
                                ->body('An error occurred: ' . $e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
            ]);
    }
} 