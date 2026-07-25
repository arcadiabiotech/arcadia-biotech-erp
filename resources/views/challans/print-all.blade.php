<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>All Challans — {{ $dispatch->dispatch_no }}</title>
    @include('challans._style')
</head>
<body>
    @foreach($sections as $entry)
        <div @if(! $loop->last) style="page-break-after: always;" @endif>
            @php($challan = $entry['challan'])
            @php($lines = $entry['lines'])
            @if($entry['type'] === \App\Models\Challan::TYPE_DEALER)
                @include('challans._dealer-content')
            @else
                @include('challans._farmer-content')
            @endif
        </div>
    @endforeach
</body>
</html>
