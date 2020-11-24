<table>
    <thead>
    <tr>
        <th>Name</th>
        <th>Projects</th>
        <th>Processes</th>
        <th>Created at</th>
    </tr>
    </thead>
    <tbody>
    @forelse($badges as $badge)
        <tr>
            <td><a href="{{ route('badges.show', $badge) }}">{{ $badge->name }}</a></td>
            <td>{{ $badge->projects()->count() }}</td>
            <td>{!! $badge->commands ? '<span class="green">Commands</span>' : ''  !!} {!! $badge->constraints ? '<span class="green">Constraints</span>' : '' !!}</td>
            <td>{{ $badge->created_at }}</td>
        </tr>
    @empty
        <tr>
            <td colspan="5">No badges found.</td>
        </tr>
    @endforelse
    </tbody>
</table>
{{ $badges }}
