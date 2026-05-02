<div class="shop-users-drawer-content">
    @if ($shopUsers->isEmpty())
        <p class="text-muted mb-0">No users found for this shop.</p>
    @else
        <div class="table-responsive rounded">
            <table class="table table-sm table-bordered mb-0 bg-white">
                <thead class="text-uppercase">
                    <tr>
                        <th scope="col">Name</th>
                        <th scope="col">User name</th>
                        <th scope="col">Password</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($shopUsers as $u)
                        <tr>
                            <td>{{ $u->name }}</td>
                            <td>{{ $u->username }}</td>
                            <td>{{ $u->actual_password ?? '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
