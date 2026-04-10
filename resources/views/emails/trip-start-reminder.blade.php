<x-mail::message>
# Your Trip is Starting Soon!

Hi {{ $trip->user->name }},

Your trip **{{ $trip->title }}** is starting in just a few days! Get ready for an amazing adventure.

## Trip Summary

- **Dates:** {{ $trip->start_date->format('M d, Y') }} — {{ $trip->end_date->format('M d, Y') }}
- **Duration:** {{ $trip->start_date->diffInDays($trip->end_date) + 1 }} days
@if($destinations->count())
- **Destinations:** {{ $destinations->pluck('name')->join(', ') }}
@endif

## Upcoming Activities

Here's a preview of what's planned for your trip:

@forelse($itineraries->take(5) as $itinerary)
### {{ $itinerary->date->format('l, M d') }}

@forelse($itinerary->activities as $activity)
- **{{ $activity->title }}** {{ $activity->start_time ? '@ ' . \Carbon\Carbon::parse($activity->start_time)->format('h:i A') : '(All day)' }}
  
  {{ Str::limit($activity->description, 80) ?? 'No description' }}

@empty
(No activities scheduled)
@endforelse

@empty
No itineraries scheduled yet.
@endforelse

---

<x-mail::button :url="route('filament.admin.resources.travels.index')">
View Trip in Admin Panel
</x-mail::button>

Thanks,  
{{ config('app.name') }} Team
</x-mail::message>
