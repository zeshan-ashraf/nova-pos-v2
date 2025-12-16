<script>
    document.addEventListener('DOMContentLoaded', function () {
        const isParentSelect = document.getElementById('is_parent');
        const parentWrapper = document.getElementById('parent-shop-wrapper');
        const parentSelect = document.getElementById('parent_shop_id');

        if (!isParentSelect || !parentWrapper || !parentSelect) {
            return;
        }

        function toggleParentField() {
            if (isParentSelect.value === '1') {
                parentWrapper.classList.add('d-none');
                parentSelect.value = '';
            } else {
                parentWrapper.classList.remove('d-none');
            }
        }

        toggleParentField();
        isParentSelect.addEventListener('change', toggleParentField);
    });
</script>


