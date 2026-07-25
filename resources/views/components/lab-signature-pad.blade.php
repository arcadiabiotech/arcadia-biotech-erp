@props(['name' => 'signature_data', 'id' => 'lab-signature-pad'])

{{--
    Self-built canvas signature capture — no external library, matching the
    app's existing convention of inline <script> blocks in layouts/app.blade.php
    rather than a new JS bundle entry. Mouse + touch listeners are both
    wired ({ passive: false } + preventDefault() on touch) so signing works
    on a phone/tablet without the page scrolling underneath the finger.
--}}
<div>
    <div class="flex items-center justify-between">
        <label class="text-sm font-semibold text-slate-700">Supervisor signature</label>
        <button type="button" id="{{ $id }}_clear" class="text-xs font-semibold text-blue-600 hover:text-blue-800">Clear</button>
    </div>
    <canvas id="{{ $id }}" width="500" height="160" class="mt-2 w-full touch-none rounded-xl border border-slate-300 bg-slate-50" style="height:160px;"></canvas>
    <input type="hidden" name="{{ $name }}" id="{{ $id }}_input">
    <p class="mt-1 text-xs text-slate-400">Sign above to confirm this decision.</p>
</div>

<script>
    (function () {
        var canvas = document.getElementById('{{ $id }}');
        var input = document.getElementById('{{ $id }}_input');
        var ctx = canvas.getContext('2d');
        ctx.lineWidth = 2;
        ctx.lineCap = 'round';
        ctx.strokeStyle = '#0f172a';

        var drawing = false;

        function pos(e) {
            var rect = canvas.getBoundingClientRect();
            var scaleX = canvas.width / rect.width;
            var scaleY = canvas.height / rect.height;
            var point = e.touches ? e.touches[0] : e;
            return { x: (point.clientX - rect.left) * scaleX, y: (point.clientY - rect.top) * scaleY };
        }

        function start(e) {
            e.preventDefault();
            drawing = true;
            var p = pos(e);
            ctx.beginPath();
            ctx.moveTo(p.x, p.y);
        }

        function move(e) {
            if (! drawing) return;
            e.preventDefault();
            var p = pos(e);
            ctx.lineTo(p.x, p.y);
            ctx.stroke();
        }

        function end() {
            if (! drawing) return;
            drawing = false;
            input.value = canvas.toDataURL('image/png');
        }

        canvas.addEventListener('mousedown', start);
        canvas.addEventListener('mousemove', move);
        window.addEventListener('mouseup', end);

        canvas.addEventListener('touchstart', start, { passive: false });
        canvas.addEventListener('touchmove', move, { passive: false });
        canvas.addEventListener('touchend', end, { passive: false });

        document.getElementById('{{ $id }}_clear').addEventListener('click', function () {
            ctx.clearRect(0, 0, canvas.width, canvas.height);
            input.value = '';
        });
    })();
</script>
