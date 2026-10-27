<?php
namespace App\Services;

class YouTubeEmbed {
 public static function url(?string $url): ?string {
  if (!$url) return null;
  $parts=parse_url($url);
  if (!is_array($parts) || !in_array(strtolower($parts['scheme'] ?? ''),['http','https'],true) || isset($parts['user']) || isset($parts['pass'])) return null;
  $host=strtolower($parts['host'] ?? ''); $path=$parts['path'] ?? ''; $id=null;
  if ($host==='youtu.be') {if (preg_match('~^/([A-Za-z0-9_-]{11})/?$~D',$path,$match)) $id=$match[1];}
  elseif (in_array($host,['youtube.com','www.youtube.com','m.youtube.com','youtube-nocookie.com','www.youtube-nocookie.com'],true)) {
   if ($path==='/watch') {parse_str($parts['query'] ?? '',$query);$id=$query['v'] ?? null;}
   elseif (preg_match('~^/(?:embed|shorts|live)/([A-Za-z0-9_-]{11})/?$~D',$path,$match)) $id=$match[1];
  }
  if (!is_string($id) || !preg_match('/^[A-Za-z0-9_-]{11}$/D',$id)) return null;
  return 'https://www.youtube-nocookie.com/embed/'.$id;
 }
}
