document.addEventListener("DOMContentLoaded", function () {
    // Initialize Chart.js
    const ctx = document.getElementById("expenseChart").getContext("2d");
    new Chart(ctx, {
        type: "line",
        data: {
            labels: ["Jan", "Feb", "Mar"],
            datasets: [
                {
                    label: "Total Expense",
                    data: [5000, 7000, 3000],
                    borderColor: "red",
                    fill: false
                },
                {
                    label: "Total Gain",
                    data: [2000, 5000, 8000],
                    borderColor: "blue",
                    fill: false
                }
            ]
        }
    });

    // Fetch Purchases on Page Load
    fetchPurchases();

    // Search filter for purchases
    document.getElementById("search").addEventListener("input", function () {
        let search = this.value.toLowerCase();
        document.querySelectorAll("#purchasesBody tr").forEach(row => {
            let text = row.textContent.toLowerCase();
            row.style.display = text.includes(search) ? "" : "none";
        });
    });

    // Handle Purchase Form Submission
    document.getElementById("purchaseForm").addEventListener("submit", function (e) {
        e.preventDefault();
        let formData = new FormData(this);

        fetch("php/add_purchase.php", {
            method: "POST",
            body: formData
        })
        .then(response => response.text())
        .then(data => {
            if (data.trim() === "Success") {
                this.reset();
                fetchPurchases();
            } else {
                alert("Error: " + data);
            }
        })
        .catch(error => console.error("Fetch Error: ", error));
    });
});
function fetchPurchases() {
    fetch("php/fetch_purchases.php")
        .then(response => response.json())
        .then(data => {
            let purchasesBody = document.getElementById("purchasesBody");
            purchasesBody.innerHTML = ""; // Clear table before updating

            data.forEach((purchase, index) => {
                let row = document.createElement("tr");
                row.innerHTML = `
                    <td>${index + 1}</td>
                    <td><input type="text" value="${purchase.supplier}" id="supplier-${purchase.id}"></td>
                    <td><input type="date" value="${purchase.date}" id="date-${purchase.id}"></td>
                    <td><input type="number" value="${purchase.amount}" id="amount-${purchase.id}"></td>
                    <td>
                        <select id="status-${purchase.id}">
                            <option value="Paid" ${purchase.status === "Paid" ? "selected" : ""}>Paid</option>
                            <option value="Due" ${purchase.status === "Due" ? "selected" : ""}>Due</option>
                        </select>
                    </td>
                    <td>
                        <button class="update-btn" onclick="updatePurchase(${purchase.id})">Update</button>
                        <button class="delete-btn" onclick="deletePurchase(${purchase.id})" style="color:red;">Delete</button>
                    </td>
                `;
                purchasesBody.appendChild(row);
            });
        })
        .catch(error => console.error("Error fetching purchases:", error));
}


// Update purchase entry
function updatePurchase(id) {
    let formData = new FormData();
    formData.append("id", id);
    formData.append("supplier", document.getElementById(`supplier-${id}`).value);
    formData.append("date", document.getElementById(`date-${id}`).value);
    formData.append("amount", document.getElementById(`amount-${id}`).value);
    formData.append("status", document.getElementById(`status-${id}`).value);

    fetch("php/update_purchase.php", {
        method: "POST",
        body: formData
    })
    .then(response => response.text())
    .then(data => {
        if (data.trim() === "Success") {
            alert("Purchase updated successfully!");
            fetchPurchases(); // Refresh data
        } else {
            alert("Error: " + data);
        }
    })
    .catch(error => console.error("Error updating purchase:", error));
}

// Delete purchase entry
function deletePurchase(id) {
    if (!confirm("Are you sure you want to delete this purchase?")) return;

    let formData = new FormData();
    formData.append("id", id);

    fetch("php/delete_purchase.php", {
        method: "POST",
        body: formData
    })
    .then(response => response.text())
    .then(data => {
        if (data.trim() === "Success") {
            alert("Purchase deleted successfully!");
            fetchPurchases(); // Refresh data
        } else {
            alert("Error: " + data);
        }
    })
    .catch(error => console.error("Error deleting purchase:", error));
}
