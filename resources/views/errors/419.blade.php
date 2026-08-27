@extends('layouts.customer')

@section('title', 'انتهت الجلسة | بيت وأسلوب — Bayt & Style')
@section('description', 'انتهت جلسة التصفح الحالية في بيت وأسلوب. أعد تحميل الصفحة ثم حاول مرة أخرى.')

@section('content')
<section class="saved-surface shell" aria-labelledby="session-expired-title">
    <nav class="breadcrumbs" aria-label="مسار التنقل">
        <a href="{{ route('home') }}">الرئيسية</a><span>‹</span>
        <span aria-current="page">انتهت الجلسة</span>
    </nav>

    <div class="saved-empty" role="status">
        <p class="eyebrow">خطأ 419</p>
        <h1 id="session-expired-title">انتهت جلسة التصفح</h1>
        <p>انتهت صلاحية الجلسة قبل إكمال الإجراء المطلوب.</p>
        <p>أعد تحميل الصفحة للحصول على جلسة جديدة، ثم أعد المحاولة. لن يتم اعتماد الإجراء السابق تلقائيًا.</p>
        <div class="hero-actions">
            <a class="button button-primary" href="{{ url()->current() }}">إعادة تحميل الصفحة</a>
            <a class="button button-ghost" href="{{ route('home') }}">العودة إلى الرئيسية</a>
        </div>
    </div>
</section>
@endsection
