document.addEventListener("DOMContentLoaded", function(){
  console.log("DOM loaded, initializing event listeners.");

  // Delete row functionality (if needed)
  document.addEventListener("click", function(e) {
    if(e.target && e.target.classList.contains("deleteRow")){
      e.target.closest("tr").remove();
      console.log("A row was deleted.");
    }
  });
  
  // Event delegation for the Save Data button
  document.addEventListener("click", function(e) {
    if(e.target && e.target.id === "saveData"){
      console.log("Save Data button clicked (via delegation).");
      
      let table = document.querySelector("form#editForm table");
      if (!table) {
        console.error("No table found in form.");
        return;
      }
      
      let data = [];
      
      // Loop through each row except header (starting at index 1)
      for (let i = 1; i < table.rows.length; i++){
        let row = table.rows[i];
        let rowData = [];
        // Loop through each cell except the last "Action" cell
        for (let j = 0; j < row.cells.length - 1; j++){
          // Get the cell value as-is (with commas if present)
          rowData.push(row.cells[j].innerText.trim());
        }
        data.push(rowData);
      }
      
      console.log("Data to send:", JSON.stringify(data));
      
      // Adjust the URL as needed
      let saveUrl = "save_data.php"; 
      
      fetch(saveUrl, {
        method: "POST",
        headers: {"Content-Type": "application/json"},
        body: JSON.stringify({ tableData: data })
      })
      .then(response => {
        console.log("Server response status:", response.status);
        return response.json();
      })
      .then(result => {
        console.log("Response from server:", result);
        alert(result.message);
      })
      .catch(error => {
        console.error("Error during fetch:", error);
        alert("Error saving data");
      });
    }
  });
});

console.log("Script file loaded.");
