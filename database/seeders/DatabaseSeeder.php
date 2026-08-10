<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductMedia;
use App\Models\Variant;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $categories = collect([
            ['name_ar' => 'المقاعد', 'slug' => 'seating', 'description_ar' => 'قطع جلوس هادئة للمساحات اليومية.', 'sort_order' => 10],
            ['name_ar' => 'الإضاءة', 'slug' => 'lighting', 'description_ar' => 'إضاءة منزلية بطابع دافئ ومتزن.', 'sort_order' => 20],
            ['name_ar' => 'الطاولات', 'slug' => 'tables', 'description_ar' => 'طاولات عملية بخامات طبيعية.', 'sort_order' => 30],
            ['name_ar' => 'المنسوجات', 'slug' => 'textiles', 'description_ar' => 'تفاصيل ناعمة تضيف الراحة والدفء.', 'sort_order' => 40],
            ['name_ar' => 'الديكور', 'slug' => 'decor', 'description_ar' => 'لمسات مختارة تكمل المساحة بهدوء.', 'sort_order' => 50],
        ])->mapWithKeys(function (array $data) {
            $category = Category::create($data);
            return [$data['slug'] => $category];
        });

        $products = [
            [
                'category' => 'seating', 'name_ar' => 'كرسي استرخاء مخملي', 'slug' => 'olive-velvet-lounge-chair',
                'short' => 'قطعة ذات حضور هادئ وخطوط ناعمة، مصممة لتنسجم بصريًا مع مساحات المعيشة الدافئة.',
                'description' => 'كرسي استرخاء بلون زيتوني هادئ وملمس مخملي ظاهر، مع هيئة مستديرة تمنحه حضورًا بصريًا مريحًا داخل مساحات الجلوس.',
                'details' => 'خيار بيع واحد معروض في هذه المرحلة. لا تتضمن الصفحة أي مصفوفة خيارات أو تخصيصات من نطاق S04.',
                'material' => 'مخمل', 'room' => 'المعيشة', 'featured' => true, 'sku' => 'BAS-CHAIR-OLV-01', 'variant' => 'مخمل زيتوني', 'price' => 1950,
                'media' => [
                    ['images/products/chair-main.jpg', 'كرسي استرخاء مخملي بلون زيتوني', 0],
                    ['images/products/chair-detail-side.jpg', 'منظر جانبي لكرسي الاسترخاء المخملي', 1],
                    ['images/products/chair-detail-seat.jpg', 'تفصيل المقعد والخامة المخملية', 2],
                    ['images/products/chair-detail-back.jpg', 'تفصيل ظهر كرسي الاسترخاء', 3],
                ],
            ],
            [
                'category' => 'lighting', 'name_ar' => 'مصباح طاولة سيراميك', 'slug' => 'ceramic-table-lamp',
                'short' => 'إضاءة هادئة بقاعدة سيراميك مطفية وتكوين بسيط.',
                'description' => 'مصباح طاولة متزن يضيف إضاءة دافئة من دون حضور بصري صاخب.',
                'details' => 'قطعة إضاءة تجريبية ضمن بيانات التطوير فقط.',
                'material' => 'سيراميك', 'room' => 'غرفة النوم', 'featured' => true, 'sku' => 'BAS-LAMP-CER-01', 'variant' => 'سيراميك زيتوني', 'price' => 420,
                'media' => [['images/products/product-lamp.jpg', 'مصباح طاولة سيراميك', 0]],
            ],
            [
                'category' => 'tables', 'name_ar' => 'طاولة قهوة خشب طبيعي', 'slug' => 'natural-wood-coffee-table',
                'short' => 'طاولة منخفضة بخط دائري وخشب داكن لمساحات الجلوس.',
                'description' => 'طاولة قهوة عملية ذات حضور بسيط وخامة خشبية دافئة.',
                'details' => 'بيانات عرض وتطوير وليست كتالوجًا إنتاجيًا.',
                'material' => 'خشب', 'room' => 'المعيشة', 'featured' => true, 'sku' => 'BAS-TABLE-WOOD-01', 'variant' => 'خشب داكن', 'price' => 1290,
                'media' => [['images/products/product-table.jpg', 'طاولة قهوة خشب طبيعي', 0]],
            ],
            [
                'category' => 'textiles', 'name_ar' => 'وسادة نسيج محبوك', 'slug' => 'knitted-textile-cushion',
                'short' => 'وسادة محايدة بنسيج واضح تضيف طبقة مريحة للمساحة.',
                'description' => 'وسادة نسيج محبوك بلون رملي هادئ.',
                'details' => 'بيانات تجريبية للتطوير.',
                'material' => 'نسيج', 'room' => 'المعيشة', 'featured' => true, 'sku' => 'BAS-CUSHION-KNIT-01', 'variant' => 'نسيج رملي', 'price' => 195,
                'media' => [['images/products/product-pillow.jpg', 'وسادة نسيج محبوك', 0]],
            ],
            [
                'category' => 'seating', 'name_ar' => 'كرسي طعام خشبي', 'slug' => 'wood-dining-chair',
                'short' => 'كرسي طعام بخطوط واضحة وخامة طبيعية.', 'description' => 'كرسي بسيط لمائدة يومية هادئة.',
                'details' => 'بيانات تجريبية للتطوير.', 'material' => 'خشب', 'room' => 'الطعام والضيافة', 'featured' => false,
                'sku' => 'BAS-DINING-CHAIR-01', 'variant' => 'خشب طبيعي', 'price' => 680,
                'media' => [['images/editorial/dining.jpg', 'كرسي طعام خشبي ضمن مشهد مائدة', 0]],
            ],
            [
                'category' => 'lighting', 'name_ar' => 'وحدة إضاءة معلقة', 'slug' => 'black-pendant-light',
                'short' => 'إضاءة معلقة سوداء بهيئة دقيقة للمائدة.', 'description' => 'وحدة إضاءة معلقة ذات حضور رسومي هادئ.',
                'details' => 'بيانات تجريبية للتطوير.', 'material' => 'معدن', 'room' => 'الطعام والضيافة', 'featured' => false,
                'sku' => 'BAS-PENDANT-BLK-01', 'variant' => 'معدن أسود', 'price' => 540,
                'media' => [['images/editorial/lighting.jpg', 'وحدة إضاءة معلقة سوداء', 0]],
            ],
            [
                'category' => 'decor', 'name_ar' => 'مزهرية حجرية هادئة', 'slug' => 'stone-vase',
                'short' => 'مزهرية بسطح مطفي لتنسيقات بسيطة.', 'description' => 'قطعة ديكور ذات كتلة هادئة ولون محايد.',
                'details' => 'بيانات تجريبية للتطوير.', 'material' => 'حجر', 'room' => 'المعيشة', 'featured' => false,
                'sku' => 'BAS-VASE-STONE-01', 'variant' => 'حجر فاتح', 'price' => 360,
                'media' => [['images/editorial/seasonal.jpg', 'مزهرية حجرية ضمن تنسيق موسمي', 0]],
            ],
            [
                'category' => 'tables', 'name_ar' => 'طاولة جانبية مستديرة', 'slug' => 'round-side-table',
                'short' => 'طاولة جانبية صغيرة للمساحات الهادئة.', 'description' => 'طاولة جانبية ذات قاعدة بسيطة وسطح دائري.',
                'details' => 'بيانات تجريبية للتطوير.', 'material' => 'خشب', 'room' => 'غرفة النوم', 'featured' => false,
                'sku' => 'BAS-SIDE-TABLE-01', 'variant' => 'خشب جوزي', 'price' => 890,
                'media' => [['images/editorial/bedroom.jpg', 'طاولة جانبية ضمن غرفة نوم هادئة', 0]],
            ],
            [
                'category' => 'textiles', 'name_ar' => 'غطاء خفيف للكنبة', 'slug' => 'soft-sofa-throw',
                'short' => 'نسيج خفيف بطابع طبيعي وملمس ناعم.', 'description' => 'غطاء بسيط يضيف طبقة ملمسية إلى جلسة المعيشة.',
                'details' => 'بيانات تجريبية للتطوير.', 'material' => 'نسيج', 'room' => 'المعيشة', 'featured' => false,
                'sku' => 'BAS-THROW-SAND-01', 'variant' => 'نسيج رملي', 'price' => 250,
                'media' => [['images/editorial/living.jpg', 'غطاء نسيجي ضمن غرفة معيشة', 0]],
            ],
            [
                'category' => 'decor', 'name_ar' => 'كونسول حجري', 'slug' => 'stone-console',
                'short' => 'قطعة كبيرة بخط هادئ لمداخل المنزل.', 'description' => 'كونسول بسيط يوازن بين الكتلة والفراغ.',
                'details' => 'بيانات تجريبية للتطوير.', 'material' => 'حجر', 'room' => 'المدخل', 'featured' => false,
                'sku' => 'BAS-CONSOLE-STONE-01', 'variant' => 'حجر رملي', 'price' => 1650,
                'media' => [['images/editorial/hero.jpg', 'كونسول حجري ضمن مساحة منزلية دافئة', 0]],
            ],
        ];

        foreach ($products as $index => $data) {
            $product = Product::create([
                'category_id' => $categories[$data['category']]->id,
                'name_ar' => $data['name_ar'],
                'slug' => $data['slug'],
                'short_description_ar' => $data['short'],
                'description_ar' => $data['description'],
                'details_ar' => $data['details'],
                'material_ar' => $data['material'],
                'room_ar' => $data['room'],
                'is_featured' => $data['featured'],
                'published_at' => now()->subDays(20 - $index),
            ]);

            Variant::create([
                'product_id' => $product->id,
                'sku' => $data['sku'],
                'name_ar' => $data['variant'],
                'price' => $data['price'],
                'currency' => 'SAR',
                'inventory_quantity' => 25 + $index,
                'is_default' => true,
                'is_active' => true,
            ]);

            foreach ($data['media'] as [$path, $alt, $sortOrder]) {
                ProductMedia::create([
                    'product_id' => $product->id,
                    'path' => $path,
                    'alt_ar' => $alt,
                    'sort_order' => $sortOrder,
                ]);
            }
        }
    }
}
