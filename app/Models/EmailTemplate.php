<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmailTemplate extends Model
{
    protected $table = 'email_templates';

    protected $fillable = [
        'key',
        'name',
        'description',
        'subject',
        'html_body',
        'text_body',
        'placeholders',
        'is_active',
        'created_by',
    ];

    protected $casts = [
        'placeholders' => 'array',
        'is_active' => 'boolean',
    ];

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function renderSubject($data = []): string
    {
        return $this->replace($this->subject, $data);
    }

    public function renderHtmlBody($data = []): string
    {
        return $this->replace($this->html_body, $data);
    }

    public function renderTextBody($data = []): string
    {
        return $this->replace($this->text_body ?? '', $data);
    }

    private function replace($text, $data): string
    {
        foreach ($data as $key => $value) {
            $text = str_replace('{{'.$key.'}}', (string) $value, $text);
        }
        return $text;
    }
}