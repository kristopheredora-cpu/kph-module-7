// ==========================================================
// KameraPH UI Kit  |  api.js
// Helpers for calling your API. Needs config.js loaded first.
//   apiGet("/promotions.php")                       -> read
//   apiSend("POST", "/promotions.php", {...})       -> create
//   apiSend("PUT",  "/promotions.php?id=1", {...})  -> change
// If the API answers with an error, these throw an Error whose
// message is the API's own message, so your page can show it.
// ==========================================================

async function request(method, path, body) {
  let res;
  try {
    res = await fetch(KPH.api + path, {
      method: method,
      headers: { "Content-Type": "application/json", "X-Test-User-Id": KPH.testUserId },
      body: body === undefined ? undefined : JSON.stringify(body)
    });
  } catch (e) {
    throw new Error("Cannot reach the server. Check that Apache is running and the address in config.js is correct.");
  }
  let data = null;
  try { data = await res.json(); } catch (e) { /* the answer was not JSON */ }
  if (!res.ok) {
    throw new Error((data && data.error) || "Something went wrong (status " + res.status + ").");
  }
  return data;
}

function apiGet(path) { return request("GET", path); }
function apiSend(method, path, body) { return request(method, path, body); }

// Makes text from the database safe to put inside HTML.
// ALWAYS use it on text that comes from the API.
function escapeHtml(text) {
  const d = document.createElement("div");
  d.textContent = text == null ? "" : String(text);
  return d.innerHTML;
}

// 18500 -> "₱18,500"     18500.5 -> "₱18,500.50"
function formatPeso(amount) {
  const n = Number(amount);
  if (Number.isNaN(n)) return "";
  return "₱" + n.toLocaleString("en-PH", { minimumFractionDigits: 0, maximumFractionDigits: 2 });
}
