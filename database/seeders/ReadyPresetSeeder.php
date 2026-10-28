<?php

namespace Database\Seeders;

use App\Models\ReadyPreset;
use Illuminate\Database\Seeder;

class ReadyPresetSeeder extends Seeder
{
    public function run(): void
    {
        $descriptions = [
            'تقرير فحص لوحة التوزيع الكهربائية للعقار.',
            'تم فحص لوحة التوزيع والتأكد من سلامة القواطع والكابلات.',
            'لوحة التوزيع في الطابق الأرضي — حالة عامة جيدة مع ملاحظات بسيطة.',
        ];

        foreach ($descriptions as $i => $body) {
            ReadyPreset::query()->firstOrCreate(
                ['type' => 'description', 'body' => $body],
                ['color' => 'none', 'sort_order' => $i + 1]
            );
        }

        $comments = [
            ['body' => 'يفضل عمل صيانة دورية بواسطة كهربائي معتمد.', 'color' => 'none'],
            ['body' => 'يجب استبدال القاطع غير المطابق لمقاس الكابل فوراً.', 'color' => 'red'],
            ['body' => 'التأريض سليم والقراءات ضمن المعدل المقبول.', 'color' => 'green'],
            ['body' => 'يُنصح بإضافة ملصقات تعريف واضحة على الدوائر.', 'color' => 'none'],
        ];

        foreach ($comments as $i => $row) {
            ReadyPreset::query()->firstOrCreate(
                ['type' => 'comment', 'body' => $row['body']],
                ['color' => $row['color'], 'sort_order' => $i + 1]
            );
        }
    }
}
