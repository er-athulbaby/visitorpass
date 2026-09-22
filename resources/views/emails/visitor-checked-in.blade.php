<p>{{ $visit->visitor->name }} has arrived{{ $visit->visitor->company_name ? ' from '.$visit->visitor->company_name : '' }}.</p>
<p>Checked in at {{ $visit->check_in_at->format('M j, Y g:i A') }}.</p>
