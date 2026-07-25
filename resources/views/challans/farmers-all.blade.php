<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Farmer Challans — {{ $dispatch->dispatch_no }}</title>
    @include('challans._style')
</head>
<body>
    @foreach($farmerChallans as $entry)
        <div @if(! $loop->last) style="page-break-after: always;" @endif>
            @php($challan = $entry['challan'])
            @php($lines = $entry['lines'])
            @include('challans._farmer-content')
        </div>
    @endforeach
</body>
</html>
