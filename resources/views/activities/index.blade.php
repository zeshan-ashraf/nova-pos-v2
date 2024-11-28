@extends('dashboard.body.main')

@section('container')
<div class="container-fluid">
    <div class="row">
        <div class="col-lg-12">
            @if (session()->has('success'))
                <div class="alert text-white bg-success" role="alert">
                    <div class="iq-alert-text">{{ session('success') }}</div>
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                    <i class="ri-close-line"></i>
                    </button>
                </div>
            @endif
            @if (session()->has('error'))
                <div class="alert text-white bg-danger" role="alert">
                    <div class="iq-alert-text">{{ session('success') }}</div>
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                    <i class="ri-close-line"></i>
                    </button>
                </div>
            @endif
            <div class="d-flex flex-wrap align-items-center justify-content-between mb-4">
                <div>
                    <h4 class="mb-3">Activity List</h4>
                    {{--  <p class="mb-0">A product dashboard lets you easily gather and visualize product data from optimizing <br>
                        the product experience, ensuring product retention. </p>  --}}
                </div>
                <div>
                <a href="{{ route('activities.create') }}" class="btn btn-primary add-list">Add Activity</a>
                </div>
            </div>
        </div>

        <div class="col-lg-12">
            <form action="{{ route('activities.index') }}" method="get">
                <div class="d-flex flex-wrap align-items-center justify-content-between">
                    <div class="form-group row">
                        <label for="row" class="col-sm-3 align-self-center">Row:</label>
                        <div class="col-sm-9">
                            <select class="form-control" name="row">
                                <option value="10" @if(request('row') == '10')selected="selected"@endif>10</option>
                                <option value="25" @if(request('row') == '25')selected="selected"@endif>25</option>
                                <option value="50" @if(request('row') == '50')selected="selected"@endif>50</option>
                                <option value="100" @if(request('row') == '100')selected="selected"@endif>100</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-group row">
                        <label class="control-label col-sm-3 align-self-center" for="search">Search:</label>
                        <div class="input-group col-sm-8">
                            <input type="text" id="search" class="form-control" name="search" placeholder="Search activity" value="{{ request('search') }}">
                            <div class="input-group-append">
                                <button type="button" id="search-btn" class="input-group-text bg-primary"><i class="las la-search"></i></button>
                                <a href="{{ route('activities.index') }}" class="input-group-text bg-danger"><i class="las la-trash"></i></a>
                            </div>
                        </div>
                    </div>

                </div>
            </form>
        </div>



        <div class="col-lg-12">
            <div class="table-responsive rounded mb-3">
                <table class="table mb-0">
                    <thead class="bg-white text-uppercase">
                        <tr class="ligth ligth-data">
                            <th>No.</th>
                            <th>Photo</th>
                            <th>@sortablelink('title', 'Title')</th>
                            <th>@sortablelink('description', 'Description')</th>
                            <th>@sortablelink('customer.name', 'Customer')</th>
                            <th>@sortablelink('date', 'Date')</th>
                            <th>@sortablelink('activity_cost', 'Activity Cost')</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody class="ligth-body" id="activity-table-body">
                        @forelse ($activities as $activity)
                            <tr>
                                <td>{{ (($activities->currentPage() - 1) * $activities->perPage()) + $loop->iteration }}</td> <!-- Correct serial number -->
                                <td>
                                    @if (is_array($activity->images))
                                        @foreach ($activity->images as $image)
                                            <img class="avatar-60 rounded" src="{{ asset('storage/' . $image) }}" alt="Activity Image">
                                        @endforeach
                                    @else
                                        <img class="avatar-60 rounded" src="{{ asset('assets/images/product/default.webp') }}" alt="Default Image">
                                    @endif
                                </td>
                                <td>{{ $activity->title }}</td>
                                <td>{{ Str::limit($activity->description, 20) }}</td>
                                <td>{{ $activity->customer->name }}</td>
                                <td>{{ $activity->date }}</td>
                                <td>{{ $activity->activity_cost }}</td>
                                <td>
                                    <form action="{{ route('activities.destroy', $activity->id) }}" method="POST" style="margin-bottom: 5px">
                                        @method('delete')
                                        @csrf
                                        <div class="d-flex align-items-center list-action">
                                            <a class="btn btn-info mr-2" data-toggle="tooltip" data-placement="top" title="View"
                                               href="{{ route('activities.show', $activity->id) }}"><i class="ri-eye-line mr-0"></i></a>
                                            <a class="btn btn-success mr-2" data-toggle="tooltip" data-placement="top" title="Edit"
                                               href="{{ route('activities.edit', $activity->id) }}"><i class="ri-pencil-line mr-0"></i></a>
                                            <button type="submit" class="btn btn-warning mr-2 border-none" onclick="return confirm('Are you sure you want to delete this record?')" data-toggle="tooltip" data-placement="top" title="Delete"><i class="ri-delete-bin-line mr-0"></i></button>
                                        </div>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center">
                                    <div class="alert text-white bg-danger" role="alert">
                                        <div class="iq-alert-text">No Activities Found.</div>
                                        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                            <i class="ri-close-line"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <!-- Pagination Links -->
            <div class="pagination" id="pagination">
                {{ $activities->appends(request()->query())->links() }} <!-- Preserve query parameters when paginating -->
            </div>
        </div>


    </div>
    <!-- Page end  -->
</div>

@endsection
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
    $(document).ready(function() {
        $('#search-btn').on('click', function() {
            var searchQuery = $('#search').val();

            $.ajax({
                url: "{{ route('activities.search') }}",
                method: 'GET',
                data: { search: searchQuery },
                success: function(response) {
                    var activityList = $('#activity-table-body');
                    activityList.empty();

                    if (response.activities.data.length > 0) {
                        $.each(response.activities.data, function(index, activity) {
                            // Get the current page and items per page from the API response (assuming it's included in the response)
                            var currentPage = response.activities.current_page;
                            var perPage = response.activities.per_page;

                            // Calculate the serial number based on current page and perPage
                            var serialNumber = (currentPage - 1) * perPage + index + 1;

                            var truncatedDescription = activity.description.length > 20 ? activity.description.substring(0, 20) + '...' : activity.description;

                            var imagesHtml = '';
                            if (activity.images && activity.images.length > 0) {
                                $.each(activity.images, function(i, image) {
                                    imagesHtml += `<img class="avatar-60 rounded" src="/storage/${image}" alt="Activity Image" style="margin-right: 5px;">`;
                                });
                            } else {
                                imagesHtml = `<img class="avatar-60 rounded" src="/assets/images/product/default.webp" alt="Default Image">`;
                            }

                            var actionButtons = `
                                <div class="d-flex align-items-center list-action">
                                    <!-- View Button -->
                                    <a class="btn btn-info mr-2" data-toggle="tooltip" data-placement="top" title="View" href="/activities/${activity.id}">
                                        <i class="ri-eye-line mr-0"></i>
                                    </a>

                                    <!-- Edit Button -->
                                    <a class="btn btn-success mr-2" data-toggle="tooltip" data-placement="top" title="Edit" href="/activities/${activity.id}/edit">
                                        <i class="ri-pencil-line mr-0"></i>
                                    </a>

                                    <!-- Delete Button -->
                                    <button type="button" class="btn btn-warning mr-2" onclick="deleteActivity(${activity.id})" data-toggle="tooltip" data-placement="top" title="Delete">
                                        <i class="ri-delete-bin-line mr-0"></i>
                                    </button>
                                </div>
                            `;

                            var row = `
                                <tr>
                                    <td>${serialNumber}</td> <!-- Serial number adjusted for pagination -->
                                    <td>
                                        ${imagesHtml} <!-- Display all images -->
                                    </td>
                                    <td>${activity.title}</td>
                                    <td>${truncatedDescription}</td> <!-- Truncated description -->
                                    <td>${activity.customer ? activity.customer.name : 'No Customer'}</td>
                                    <td>${activity.date}</td>
                                    <td>${activity.activity_cost}</td>
                                    <td>
                                        ${actionButtons} <!-- Insert action buttons -->
                                    </td>
                                </tr>
                            `;
                            $('#activity-table-body').append(row);
                        });
                    } else {
                        activityList.append('<tr><td colspan="7" class="text-center">No Activities Found.</td></tr>');
                    }

                    $('#pagination').html(response.activities.links);
                },
                error: function() {
                    alert("Error occurred while searching. Please try again.");
                }
            });
        });

        $('#search').on('input', function() {
            $('#search-btn').click();
        });
    });
    function deleteActivity(activityId) {
        if (confirm('Are you sure you want to delete this record?')) {
            var form = document.createElement('form');
            form.method = 'POST';
            form.action = `/activities/${activityId}`;
            var csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
            var csrfField = document.createElement('input');
            csrfField.type = 'hidden';
            csrfField.name = '_token';
            csrfField.value = csrfToken;
            form.appendChild(csrfField);

            var methodField = document.createElement('input');
            methodField.type = 'hidden';
            methodField.name = '_method';
            methodField.value = 'DELETE';
            form.appendChild(methodField);

            document.body.appendChild(form);
            form.submit();
        }
    }

</script>


