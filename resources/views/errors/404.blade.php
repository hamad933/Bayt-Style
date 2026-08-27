@extends('layouts.customer')

@section('title', 'الصفحة غير موجودة | بيت وأسلوب — Bayt & Style')
@section('description', 'تعذر العثور على الصفحة المطلوبة في بيت وأسلوب.')

@section('content')
<section class="saved-surface shell" aria-labelledby="not-found-title">
    <nav class="breadcrumbs" aria-label="مسار التنقل">
        <a href="{{ route('home') }}">الرئيسية</a><span>‹</span>
        <span aria-current="page">الصفحة غير موجودة</span>
    </nav>

    <div class="saved-empty" role="status">
        <p class="eyebrow">خطأ 404</p>
        <h1 id="not-found-title">الصفحة غير موجودة</h1>
        <p>تعذر العثور على الصفحة التي طلبتها.</p>
        <p>قد يكون الرابط قديمًا أو غير صحيح. يمكنك العودة إلى الرئيسية أو متابعة الاستكشاف من الكتالوج.</p>
        <div class="hero-actions">
            <a class="button button-primary" href="{{ route('home') }}">العودة إلى الرئيسية</a>
            <a class="button button-ghost" href="{{ route('catalog') }}">استعراض الكتالوج</a>
        </div>
    </div>
</section>
@endsection
