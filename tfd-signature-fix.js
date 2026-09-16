/**
 * Fix Ninja Forms signature pad fading on fast strokes.
 * Forces full black, full opacity, consistent line width during drawing.
 */
document.addEventListener('DOMContentLoaded', function() {
    function patchSignaturePad() {
        const canvases = document.querySelectorAll('.nf-element-signature canvas');
        if (!canvases.length) return;

        canvases.forEach(function(canvas) {
            if (canvas._nfFadeFixed) return;
            canvas._nfFadeFixed = true;

            const ctx = canvas.getContext('2d');

            // Intercept all drawing methods — force full black + full opacity
            const origStroke = ctx.stroke;
            const origFill = ctx.fill;

            // Patch stroke: ensure context is black + opaque before any draw
            ctx.stroke = function() {
                ctx.globalAlpha = 1;
                ctx.strokeStyle = '#000000';
                ctx.lineWidth = Math.max(ctx.lineWidth, 2); // min 2px width
                return origStroke.apply(ctx, arguments);
            };

            ctx.fill = function() {
                ctx.globalAlpha = 1;
                ctx.fillStyle = '#000000';
                return origFill.apply(ctx, arguments);
            };

            // Also patch lineTo to force consistent width on fast movements
            const origLineTo = ctx.lineTo;
            ctx.lineTo = function(x, y) {
                ctx.globalAlpha = 1;
                return origLineTo.apply(ctx, arguments);
            };

            // Patch moveTo as well
            const origMoveTo = ctx.moveTo;
            ctx.moveTo = function(x, y) {
                ctx.globalAlpha = 1;
                return origMoveTo.apply(ctx, arguments);
            };

            // Patch beginPath to reset opacity
            const origBeginPath = ctx.beginPath;
            ctx.beginPath = function() {
                ctx.globalAlpha = 1;
                return origBeginPath.apply(ctx, arguments);
            };

            console.log('[TFCAP] Signature pad patched:', canvas.id || 'unnamed');
        });
    }

    // Try immediately, then retry
    patchSignaturePad();
    setTimeout(patchSignaturePad, 500);
    setTimeout(patchSignaturePad, 1500);
    setTimeout(patchSignaturePad, 3000);

    // Also observe for dynamically added canvases
    const observer = new MutationObserver(function() {
        patchSignaturePad();
    });
    observer.observe(document.body, { childList: true, subtree: true });
});
