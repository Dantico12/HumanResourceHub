            </div>
        </div>
    </div>

    <script>
        // Calculate leave days when dates change
        document.addEventListener('DOMContentLoaded', function() {
            const startDateInput = document.getElementById('start_date');
            const endDateInput = document.getElementById('end_date');
            const calculatedDays = document.getElementById('calculated_days');

            if (startDateInput && endDateInput && calculatedDays) {
                function calculateDays() {
                    if (startDateInput.value && endDateInput.value) {
                        const start = new Date(startDateInput.value);
                        const end = new Date(endDateInput.value);

                        if (end >= start) {
                            const diffTime = Math.abs(end - start);
                            const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24)) + 1; // Include both start and end days
                            calculatedDays.value = diffDays + ' days';
                        } else {
                            calculatedDays.value = 'Invalid date range';
                        }
                    } else {
                        calculatedDays.value = '';
                    }
                }

                startDateInput.addEventListener('change', calculateDays);
                endDateInput.addEventListener('change', calculateDays);
            }

            // Set minimum date to today for leave applications
            const today = new Date().toISOString().split('T')[0];
            if (startDateInput) {
                startDateInput.min = today;
            }
            if (endDateInput) {
                endDateInput.min = today;
            }
        });
    </script>

</body>
</html>