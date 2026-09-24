<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class ElectricityReport extends Model
{
    protected $fillable = [
        'user_id',
        'title',
        'client_name',
        'address',
        'reference_code',
        'inspection_date',
        'villa_number',
        'road',
        'compound',
        'area',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'inspection_date' => 'date',
        ];
    }

    public function reportInspectionDate(): Carbon
    {
        return $this->inspection_date
            ?? $this->created_at
            ?? now();
    }

    public function reportInspectionDateFormatted(): string
    {
        return $this->reportInspectionDate()->format('Y-m-d');
    }

    public function reportNumber(): string
    {
        return $this->reference_code ?: ('E-'.$this->id);
    }

    public function propertyAddress(): string
    {
        $parts = [];
        if ($this->villa_number) {
            $parts[] = 'فيلا '.$this->villa_number;
        }
        if ($this->road) {
            $parts[] = 'طريق '.$this->road;
        }
        if ($this->compound) {
            $parts[] = 'مجمع '.$this->compound;
        }
        if ($this->area) {
            $parts[] = $this->area;
        }

        return implode('، ', $parts) ?: ($this->address ?: '---');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function sections(): HasMany
    {
        return $this->hasMany(ReportSection::class)->orderBy('sort_order')->orderBy('id');
    }
}
