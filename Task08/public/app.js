import { MinesweeperGame } from "./game.js";
import {
  createGame,
  saveResult,
  getAllResults,
  clearResults,
  addMove,
  getMoves,
} from "./db.js";
import { initParticles } from "./particles.js";

const boardEl = document.getElementById("board");
const widthInput = document.getElementById("widthInput");
const heightInput = document.getElementById("heightInput");
const minesInput = document.getElementById("minesInput");
const randomMinesCheck = document.getElementById("randomMinesCheck");

const timerEl = document.getElementById("timer");
const remainingEl = document.getElementById("remaining");
const playerInput = document.getElementById("playerName");
const flagModeBtn = document.getElementById("flagModeBtn");
const surrenderBtn = document.getElementById("surrenderBtn");

const historyModal = document.getElementById("historyModal");
const historyHeader = document.getElementById("historyHeader");
const historyBody = document.getElementById("historyBody");
const clearHistoryBtn = document.getElementById("clearHistoryBtn");
const openHistoryBtn = document.getElementById("openHistoryBtn");
const closeHistoryBtn = document.getElementById("closeHistoryBtn");

const loseBanner = document.getElementById("loseBanner");
const winBanner = document.getElementById("winBanner");
const newGameWinBtn = document.getElementById("newGameWinBtn");
const restartBtn = document.getElementById("restartBtn");
const restartBtnBanner = document.getElementById("restartBtnBanner");

const replayOverlay = document.getElementById("replayOverlay");
const replayPlayBtn = document.getElementById("replayPlayBtn");
const replayStepBtn = document.getElementById("replayStepBtn");
const replayCloseBtn = document.getElementById("replayCloseBtn");
const replayStatus = document.getElementById("replayStatus");

const settingsInputs = [widthInput, heightInput, minesInput, randomMinesCheck];

let game = null;
let timerInterval = null;
let seconds = 0;
let currentGameId = null;
let isFlaggingMode = false;
let allHistoryResults = [];
let historySortState = { column: "date", direction: "desc" };

let replayState = {
  active: false,
  timer: null,
  moves: [],
  index: 0,
  result: null,
};

function formatTime(s) {
  const m = Math.floor(s / 60)
    .toString()
    .padStart(2, "0");
  const ss = (s % 60).toString().padStart(2, "0");
  return `${m}:${ss}`;
}

function startTimer() {
  stopTimer();
  seconds = 0;
  timerEl.textContent = formatTime(seconds);
  timerInterval = setInterval(() => {
    seconds++;
    timerEl.textContent = formatTime(seconds);
  }, 1000);
}

function stopTimer() {
  if (timerInterval) clearInterval(timerInterval);
  timerInterval = null;
}

function validateInputs() {
  let w = parseInt(widthInput.value) || 10;
  let h = parseInt(heightInput.value) || 10;

  if (w < 2) w = 2;
  if (w > 20) w = 20;
  if (h < 2) h = 2;
  if (h > 20) h = 20;

  widthInput.value = w;
  heightInput.value = h;

  const maxMines = w * h - 1;
  let m = parseInt(minesInput.value) || 1;

  if (m < 1) m = 1;
  if (m > maxMines) m = maxMines;

  if (!randomMinesCheck.checked) {
    minesInput.value = m;
  }

  return { w, h, m, maxMines };
}

function toggleRandomMines() {
  const isRandom = randomMinesCheck.checked;
  minesInput.disabled = isRandom;
  if (!isRandom) {
    validateInputs();
  }
}

function newGame() {
  stopReplay();

  let { w, h, m, maxMines } = validateInputs();

  if (randomMinesCheck.checked) {
    m = Math.floor(Math.random() * maxMines) + 1;
    minesInput.value = m;
  }

  currentGameId = null;
  game = new MinesweeperGame(w, h, m);
  stopTimer();
  timerEl.textContent = "00:00";
  renderBoard();
  hideWinBanner();
  hideLoseBanner();

  if (isFlaggingMode) {
    toggleFlagMode();
  }
  toggleSettingsInputs(false);
  surrenderBtn.disabled = true;
}

function renderBoard() {
  boardEl.style.gridTemplateColumns = `repeat(${game.width}, 1fr)`;
  boardEl.innerHTML = "";

  for (let y = 0; y < game.height; y++) {
    for (let x = 0; x < game.width; x++) {
      const cell = document.createElement("div");
      cell.className = "cell hidden";
      cell.setAttribute("data-x", x);
      cell.setAttribute("data-y", y);
      cell.addEventListener("click", (e) => onCellLeftClick(x, y));
      cell.addEventListener("contextmenu", (e) => onCellRightClick(e, x, y));
      boardEl.appendChild(cell);
    }
  }
  updateRemainingUI();
}

function refreshCells() {
  if (!boardEl.children.length) return;
  for (let y = 0; y < game.height; y++) {
    for (let x = 0; x < game.width; x++) {
      const idx = y * game.width + x;
      const cellEl = boardEl.children[idx];
      const c = game.board[y][x];

      const classes = ["cell"];
      if (!c.revealed) classes.push("hidden");
      else classes.push("revealed");

      if (c.flagged) classes.push("flagged");
      if (c.mine && c.revealed) classes.push("mine");

      cellEl.className = classes.join(" ");
      cellEl.textContent = "";

      for (let i = 1; i <= 8; i++) cellEl.classList.remove(`n${i}`);

      if (c.revealed) {
        if (c.mine) {
        } else if (c.adjacent > 0) {
          cellEl.textContent = String(c.adjacent);
          cellEl.classList.add(`n${c.adjacent}`);
        }
      }
    }
  }
}

function updateRemainingUI() {
  if (!game) return;
  let flagged = 0;
  for (let y = 0; y < game.height; y++) {
    for (let x = 0; x < game.width; x++) {
      if (game.board[y][x].flagged) flagged++;
    }
  }
  remainingEl.textContent = `Мины: ${game.mineCount - flagged}`;
}

async function onCellLeftClick(x, y) {
  if (game.gameOver || replayState.active) return;

  if (!game.firstMoveDone) {
    startTimer();
    toggleSettingsInputs(true);
    surrenderBtn.disabled = false;
  }

  if (isFlaggingMode) {
    await handleFlagCell(x, y);
    return;
  }

  const changes = game.reveal(x, y);
  if (changes.length) await logMove("reveal", x, y);
  refreshCells();
  updateRemainingUI();
  if (game.gameOver) await onGameFinished(x, y);
}

async function onCellRightClick(e, x, y) {
  e.preventDefault();
  if (game.gameOver || replayState.active) return;
  await handleFlagCell(x, y);
}

async function handleFlagCell(x, y) {
  const val = game.toggleFlag(x, y);
  await logMove("flag", x, y, val);
  refreshCells();
  updateRemainingUI();
}

async function onGameFinished(hitX, hitY) {
  stopTimer();
  toggleSettingsInputs(false);
  surrenderBtn.disabled = true;

  if (!game.victory) {
    game.revealAllMines();
    const idx = hitY * game.width + hitX;
    if (boardEl.children[idx]) {
      boardEl.children[idx].classList.add("exploded");
    }
  }
  refreshCells();
  const player = (playerInput?.value || "").trim();
  await saveResult({
    width: game.width,
    height: game.height,
    mines: game.mineCount,
    seconds,
    victory: game.victory,
    player,
    seed: game.seed,
    gameId: currentGameId,
  });

  if (game.victory) showWinBanner();
  else showLoseBanner();
}

async function handleSurrender() {
  if (!game || game.gameOver) return;
  stopTimer();
  toggleSettingsInputs(false);
  surrenderBtn.disabled = true;

  game.surrender();
  refreshCells();

  const player = (playerInput?.value || "").trim();
  await saveResult({
    width: game.width,
    height: game.height,
    mines: game.mineCount,
    seconds,
    victory: false,
    player,
    seed: game.seed,
    gameId: currentGameId,
    surrendered: true,
  });
}

async function loadHistory() {
  try {
    allHistoryResults = await getAllResults();
    renderHistoryTable();
  } catch (e) {
    console.error("Error loading history:", e);
    alert("Не удалось загрузить историю.");
  }
}

function renderHistoryTable() {
  const { column, direction } = historySortState;

  const sorter = (a, b) => {
    let valA, valB;
    if (column === "size") {
      valA = a.width * a.height;
      valB = b.width * b.height;
    } else {
      valA = a[column];
      valB = b[column];
    }
    let result = 0;
    if (valA < valB) result = -1;
    if (valA > valB) result = 1;
    return direction === "asc" ? result : -result;
  };
  allHistoryResults.sort(sorter);

  historyBody.innerHTML = "";
  for (const r of allHistoryResults) {
    const tr = document.createElement("tr");
    const resultText = r.surrendered
      ? "Сдался"
      : r.victory
        ? "Победа"
        : "Проигрыш";

    tr.innerHTML = `
            <td>${r.player || "Аноним"}</td>
            <td>${new Date(r.date).toLocaleString("ru-RU")}</td>
            <td>${r.width}×${r.height}</td>
            <td>${r.mines}</td>
            <td>${r.seconds}с</td>
            <td>${resultText}</td>
            <td><button data-game-id="${r.id}" class="replayBtn secondary" style="padding: 4px 8px; font-size: 12px;">Повтор</button></td>`;
    tr.querySelector(".replayBtn").addEventListener("click", (e) => {
      const id = Number(e.currentTarget.getAttribute("data-game-id"));
      beginReplay(id);
    });
    historyBody.appendChild(tr);
  }
}

historyHeader.addEventListener("click", (e) => {
  const newColumn = e.target.dataset.sort;
  if (!newColumn) return;

  if (historySortState.column === newColumn) {
    historySortState.direction =
      historySortState.direction === "asc" ? "desc" : "asc";
  } else {
    historySortState.column = newColumn;
    historySortState.direction = "desc";
  }
  renderHistoryTable();
});

async function beginReplay(resultId) {
  closeHistoryModal();
  stopTimer();
  hideLoseBanner();
  hideWinBanner();

  const result = allHistoryResults.find((r) => r.id === resultId);
  if (!result) return;

  const moves = await getMoves(result.gameId || result.id);

  game = new MinesweeperGame(
    result.width,
    result.height,
    result.mines,
    result.seed,
  );
  renderBoard();
  boardEl.classList.add("replay-active");

  replayState = {
    active: true,
    timer: null,
    moves: moves.sort((a, b) => a.t - b.t),
    index: 0,
    result: result,
  };
  replayOverlay.classList.remove("hidden");
  replayStatus.textContent = "Режим повтора";
  replayStatus.className = "replay-status";

  replayPlayBtn.style.display = "inline-block";
  replayStepBtn.style.display = "inline-block";
}

function startReplay(auto) {
  if (!replayState.active || replayState.timer) return;

  replayStatus.textContent = "Воспроизведение...";
  if (auto) {
    replayState.timer = setInterval(stepReplay, 500);
    stepReplay();
  } else {
    stepReplay();
  }
}

function stepReplay() {
  if (replayState.index >= replayState.moves.length) {
    endReplay();
    return;
  }

  const move = replayState.moves[replayState.index++];
  if (move.type === "reveal") {
    game.reveal(move.x, move.y);
  } else if (move.type === "flag") {
    if (game.board[move.y][move.x].flagged !== move.value) {
      game.toggleFlag(move.x, move.y);
    }
  }

  refreshCells();
  updateRemainingUI();

  if (replayState.index >= replayState.moves.length) {
    endReplay();
  }
}

function endReplay() {
  if (replayState.timer) clearInterval(replayState.timer);
  replayState.timer = null;

  if (!replayState.result.victory) {
    game.revealAllMines();
    refreshCells();
  }

  if (replayState.result.surrendered) {
    replayStatus.textContent = `Повтор завершен: Игрок сдался`;
    replayStatus.className = "replay-status surrendered";
  } else {
    replayStatus.textContent = `Повтор завершен: ${replayState.result.victory ? "Победа" : "Поражение"}`;
    replayStatus.className = `replay-status ${replayState.result.victory ? "win" : "lose"}`;
  }

  replayPlayBtn.style.display = "none";
  replayStepBtn.style.display = "none";
}

function stopReplay() {
  if (replayState.timer) clearInterval(replayState.timer);
  replayState = {
    active: false,
    timer: null,
    moves: [],
    index: 0,
    result: null,
  };
  replayOverlay.classList.add("hidden");
  boardEl.classList.remove("replay-active");
  replayPlayBtn.style.display = "inline-block";
  replayStepBtn.style.display = "inline-block";
}

function toggleFlagMode() {
  isFlaggingMode = !isFlaggingMode;
  flagModeBtn.classList.toggle("active", isFlaggingMode);
}

function toggleSettingsInputs(disabled) {
  settingsInputs.forEach((input) => {
    if (input === randomMinesCheck) {
      input.disabled = disabled;
    } else if (input === minesInput) {
      if (disabled) input.disabled = true;
      else input.disabled = randomMinesCheck.checked;
    } else {
      input.disabled = disabled;
    }
  });
}

function openHistoryModal() {
  loadHistory().then(() => {
    document.body.classList.add("modal-open");
    historyModal.classList.remove("hidden");
    historyModal.setAttribute("aria-hidden", "false");
  });
}
function closeHistoryModal() {
  document.body.classList.remove("modal-open");
  historyModal.classList.add("hidden");
  historyModal.setAttribute("aria-hidden", "true");
}
function showLoseBanner() {
  loseBanner.classList.remove("hidden");
}
function hideLoseBanner() {
  loseBanner.classList.add("hidden");
}
function showWinBanner() {
  winBanner.classList.remove("hidden");
}
function hideWinBanner() {
  winBanner.classList.add("hidden");
}

async function logMove(type, x, y, value) {
  if (!currentGameId) {
    try {
      const player = (playerInput?.value || "Аноним").trim();
      const result = await createGame({
        width: game.width,
        height: game.height,
        mines: game.mineCount,
        player,
        seed: game.seed,
      });
      currentGameId = result.id;
    } catch (e) {
      console.error("Failed to create game on server:", e);
      return;
    }
  }
  await addMove(currentGameId, { t: Date.now(), type, x, y, value });
}

randomMinesCheck.addEventListener("change", toggleRandomMines);

widthInput.addEventListener("change", validateInputs);
heightInput.addEventListener("change", validateInputs);
minesInput.addEventListener("change", validateInputs);

surrenderBtn.addEventListener("click", handleSurrender);
flagModeBtn.addEventListener("click", toggleFlagMode);

openHistoryBtn.addEventListener("click", openHistoryModal);
closeHistoryBtn.addEventListener("click", closeHistoryModal);
clearHistoryBtn.addEventListener("click", async () => {
  if (confirm("Вы уверены, что хотите очистить всю историю?")) {
    await clearResults();
    await loadHistory();
  }
});

restartBtn.addEventListener("click", () => {
  hideLoseBanner();
  hideWinBanner();
  newGame();
});

if (restartBtnBanner) {
  restartBtnBanner.addEventListener("click", () => {
    hideLoseBanner();
    newGame();
  });
}

newGameWinBtn.addEventListener("click", () => {
  hideWinBanner();
  newGame();
});

replayPlayBtn.addEventListener("click", () => startReplay(true));
replayStepBtn.addEventListener("click", () => startReplay(false));
replayCloseBtn.addEventListener("click", () => {
  stopReplay();
  newGame();
});

validateInputs();
newGame();
initParticles();
