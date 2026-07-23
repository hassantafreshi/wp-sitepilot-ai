<?php
declare(strict_types=1);
namespace SitePilot\Support;

final class PdfExporter {
	public function generate( array $messages, string $name = '' ): string {
		$html = '<!DOCTYPE html><html dir="auto"><head><meta charset="utf-8"><title>Chat</title>';
		$html .= '<style>body{font-family:sans-serif;max-width:800px;margin:20px auto;color:#333}
			.msg{margin:12px 0;padding:12px;border-radius:8px;background:#f5f5f5}
			.msg--user{background:#eef2ff;border-left:3px solid #4f46e5}
			.msg--ai{background:#f9fafb;border-left:3px solid #10b981}
			.role{font-weight:700;font-size:12px;color:#666;margin-bottom:4px}
			.content{line-height:1.6}
			@page{margin:20mm}
			@media print{body{margin:0}}</style></head><body>';
		$html .= '<h2>Chat Conversation</h2><p>'.esc_html( $name ?: 'Visitor' ).' | '.gmdate( 'Y-m-d H:i:s' ).' UTC</p><hr>';
		foreach ( $messages as $m ) {
			$role = ( $m['role'] ?? 'user' ) === 'user' ? 'User' : 'AI';
			$cls = $role === 'User' ? 'msg--user' : 'msg--ai';
			$html .= '<div class="msg '.$cls.'"><div class="role">'.$role.'</div><div class="content">'.esc_html( $m['content'] ?? '' ).'</div></div>';
		}
		$html .= '</body></html>';
		return $html;
	}
}
