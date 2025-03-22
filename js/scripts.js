document.addEventListener("DOMContentLoaded", function () {
  console.log("DOM loaded, initializing event listeners.");

  // Create and append the loading screen element if it doesn't exist
  function showLoading() {
    let loadingScreen = document.getElementById("loadingScreen");
    if (!loadingScreen) {
      loadingScreen = document.createElement("div");
      loadingScreen.id = "loadingScreen";
      loadingScreen.style.position = "fixed";
      loadingScreen.style.top = "0";
      loadingScreen.style.left = "0";
      loadingScreen.style.width = "100%";
      loadingScreen.style.height = "100%";
      loadingScreen.style.background = "rgba(0, 0, 0, 0.5)";
      loadingScreen.style.color = "#fff";
      loadingScreen.style.fontSize = "2rem";
      loadingScreen.style.display = "flex";
      loadingScreen.style.alignItems = "center";
      loadingScreen.style.justifyContent = "center";
      loadingScreen.style.zIndex = "3000";
      loadingScreen.innerText = "Loading...";
      document.body.appendChild(loadingScreen);
    } else {
      loadingScreen.style.display = "flex";
    }
  }

  function hideLoading() {
    let loadingScreen = document.getElementById("loadingScreen");
    if (loadingScreen) {
      loadingScreen.style.display = "none";
    }
  }

  // Delete row functionality (if needed)
  document.addEventListener("click", function (e) {
    if (e.target && e.target.classList.contains("deleteRow")) {
      e.target.closest("tr").remove();
      console.log("A row was deleted.");
    }
  });

  // Event delegation for the Save Data button
  document.addEventListener("click", function (e) {
    if (e.target && e.target.id === "saveData") {
      console.log("Save Data button clicked (via delegation).");

      let table = document.querySelector("form#editForm table");
      if (!table) {
        console.error("No table found in form.");
        return;
      }

      let data = [];
      let rows = table.querySelectorAll("tr");

      // Loop through each row except the header (assuming the first row is the header)
      rows.forEach(function (row, rowIndex) {
        if (rowIndex === 0) return; // Skip header row
        let rowData = [];
        let cells = row.querySelectorAll("td");

        cells.forEach(function (cell) {
          let colspan = cell.getAttribute("colspan");
          colspan = colspan ? parseInt(colspan) : 1;
          let text = cell.innerText.trim();
          rowData.push(text);
          // If there is a colspan, add empty strings for the extra columns.
          for (let j = 1; j < colspan; j++) {
            rowData.push("");
          }
        });
        data.push(rowData);
      });

      console.log("Data to send:", JSON.stringify(data));

      // Show the loading overlay
      showLoading();

      // Adjust the URL as needed.
      let saveUrl = "save_data.php"; // Update this path if necessary

      fetch(saveUrl, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ tableData: data })
      })
        .then(response => {
          console.log("Server response status:", response.status);
          return response.json();
        })
        .then(result => {
          console.log("Response from server:", result);
          alert(result.message);
          hideLoading();
        })
        .catch(error => {
          console.error("Error during fetch:", error);
          alert("Error saving data");
          hideLoading();
        });
    }
  });
});

console.log("Script file loaded.");
