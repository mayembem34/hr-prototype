@php($videoUrl = \App\Services\YouTubeEmbed::url($url))
@if($videoUrl)
<div style="max-width:720px;aspect-ratio:16/9;margin:12px 0"><iframe src="{{ $videoUrl }}" title="{{ $title }}" style="width:100%;height:100%;border:0" loading="lazy" referrerpolicy="strict-origin-when-cross-origin" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" allowfullscreen></iframe></div>
@endif
