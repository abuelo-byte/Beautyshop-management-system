// Store loans data for modals
const loans = <?php echo json_encode($loans); ?>;

// Function to show the add payment modal
function showAddPaymentModal(loanId) {
    console.log('Opening modal for loan:', loanId); // Debug log
    const loan = loans.find(l => l.id === loanId);
    if (loan) {
        const remainingAmount = parseFloat(loan.amount) - parseFloat(loan.total_payments);
        document.getElementById('modal_loan_id').value = loanId;
        document.getElementById('payment_amount').max = remainingAmount;
        document.getElementById('remaining_amount_display').textContent = 
            `Maximum payment amount: Ksh ${remainingAmount.toFixed(2)}`;
        document.getElementById('remaining_amount_display').style.display = 'block';
        
        // Show the modal
        const modal = document.getElementById('addPaymentModal');
        modal.style.display = 'block';
        console.log('Modal displayed'); // Debug log
    }
}

// Close modals when clicking the X
document.querySelectorAll('.close').forEach(closeBtn => {
    closeBtn.addEventListener('click', function() {
        const modal = this.closest('.modal');
        modal.style.display = 'none';
        console.log('Modal closed by X'); // Debug log
    });
});

// Close modals when clicking outside
window.addEventListener('click', function(event) {
    if (event.target.classList.contains('modal')) {
        event.target.style.display = 'none';
        console.log('Modal closed by outside click'); // Debug log
    }
});

// Initialize modals when the page loads
document.addEventListener('DOMContentLoaded', function() {
    console.log('Page loaded, initializing modals'); // Debug log
    // Add any additional initialization code here if needed
}); 