<?php

namespace App\Enums;

enum ActivityAction: string
{
    case Created = 'created';
    case Updated = 'updated';
    case Deleted = 'deleted';
    case Restored = 'restored';
    case StatusChanged = 'status_changed';

    public function label(): string
    {
        return match ($this) {
            self::Created => 'Criação',
            self::Updated => 'Alteração',
            self::Deleted => 'Exclusão',
            self::Restored => 'Restauração',
            self::StatusChanged => 'Mudança de status',
        };
    }
}
