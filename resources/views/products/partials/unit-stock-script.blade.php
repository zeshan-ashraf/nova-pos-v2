<script>
(function() {
    function bindProductUnitInputs(unitId, stockId, warningId) {
        var unitEl = document.getElementById(unitId);
        var stockEl = document.getElementById(stockId);
        var warningEl = warningId ? document.getElementById(warningId) : null;
        if (!unitEl || !stockEl) {
            return;
        }

        function apply() {
            var isKg = unitEl.value === 'kg';
            stockEl.setAttribute('step', isKg ? '0.001' : '1');
            stockEl.setAttribute('min', '0');
            if (warningEl) {
                warningEl.setAttribute('step', isKg ? '0.001' : '1');
                warningEl.setAttribute('min', '0');
            }
        }

        unitEl.addEventListener('change', apply);
        apply();
    }

    window.bindProductUnitInputs = bindProductUnitInputs;
})();
</script>
