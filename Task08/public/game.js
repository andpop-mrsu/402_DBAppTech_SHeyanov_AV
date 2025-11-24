class RNG {
  constructor(seed = Date.now()) {
    this.seed = seed >>> 0;
  }
  next() {
    let x = this.seed;
    x ^= x << 13;
    x >>>= 0;
    x ^= x >> 17;
    x >>>= 0;
    x ^= x << 5;
    x >>>= 0;
    this.seed = x >>> 0;
    return (x >>> 0) / 0xffffffff;
  }
}

export class MinesweeperGame {
  constructor(width, height, mineCount, rngSeed = Date.now()) {
    this.width = width;
    this.height = height;
    this.mineCount = Math.min(mineCount, width * height - 1);
    this.board = Array.from(
      {
        length: height,
      },
      () =>
        Array.from(
          {
            length: width,
          },
          () => ({
            mine: false,
            adjacent: 0,
            revealed: false,
            flagged: false,
          }),
        ),
    );
    this.firstMoveDone = false;
    this.gameOver = false;
    this.victory = false;
    this.cellsRevealed = 0;
    this.rng = new RNG(rngSeed);
    this.seed = rngSeed;
    this.moveLog = [];
  }

  inBounds(x, y) {
    return x >= 0 && y >= 0 && x < this.width && y < this.height;
  }

  neighbors(x, y) {
    const coords = [];
    for (let dy = -1; dy <= 1; dy++) {
      for (let dx = -1; dx <= 1; dx++) {
        if (dx === 0 && dy === 0) continue;
        const nx = x + dx,
          ny = y + dy;
        if (this.inBounds(nx, ny)) coords.push([nx, ny]);
      }
    }
    return coords;
  }

  placeMines(safeX, safeY) {
    const forbidden = new Set([`${safeX},${safeY}`]);

    this.neighbors(safeX, safeY).forEach(([nx, ny]) =>
      forbidden.add(`${nx},${ny}`),
    );

    let placed = 0;
    const total = this.width * this.height;
    if (this.mineCount >= total - forbidden.size) {
      this.mineCount = Math.max(1, total - forbidden.size - 1);
    }

    while (placed < this.mineCount) {
      const idx = Math.floor(this.rng.next() * total);
      const x = idx % this.width;
      const y = Math.floor(idx / this.width);
      const key = `${x},${y}`;
      if (forbidden.has(key)) continue;
      const cell = this.board[y][x];
      if (!cell.mine) {
        cell.mine = true;
        placed++;
      }
    }

    for (let y = 0; y < this.height; y++) {
      for (let x = 0; x < this.width; x++) {
        const cell = this.board[y][x];
        if (cell.mine) continue;
        cell.adjacent = this.neighbors(x, y).reduce(
          (sum, [nx, ny]) => sum + (this.board[ny][nx].mine ? 1 : 0),
          0,
        );
      }
    }
  }

  reveal(x, y) {
    if (this.gameOver) return [];
    const cell = this.board[y][x];
    if (cell.revealed || cell.flagged) return [];

    if (!this.firstMoveDone) {
      this.placeMines(x, y);
      this.firstMoveDone = true;
    }

    const revealed = [];
    const stack = [[x, y]];
    while (stack.length) {
      const [cx, cy] = stack.pop();
      const c = this.board[cy][cx];
      if (c.revealed || c.flagged) continue;
      c.revealed = true;
      revealed.push([cx, cy]);
      this.cellsRevealed++;
      if (c.mine) {
        this.gameOver = true;
        this.victory = false;
        break;
      }
      if (c.adjacent === 0) {
        for (const [nx, ny] of this.neighbors(cx, cy)) {
          const n = this.board[ny][nx];
          if (!n.revealed && !n.flagged && !n.mine) stack.push([nx, ny]);
        }
      }
    }
    this.moveLog.push({
      t: Date.now(),
      type: "reveal",
      x,
      y,
    });
    this.checkWinCondition();
    return revealed;
  }

  toggleFlag(x, y) {
    if (this.gameOver) return false;
    const cell = this.board[y][x];
    if (cell.revealed) return false;
    cell.flagged = !cell.flagged;
    this.moveLog.push({
      t: Date.now(),
      type: "flag",
      x,
      y,
      value: cell.flagged,
    });
    return cell.flagged;
  }

  surrender() {
    if (this.gameOver) return;
    this.gameOver = true;
    this.victory = false;
    this.revealAllMines();
  }

  revealAllMines() {
    for (let y = 0; y < this.height; y++) {
      for (let x = 0; x < this.width; x++) {
        const c = this.board[y][x];
        if (c.mine) c.revealed = true;
      }
    }
  }

  checkWinCondition() {
    const totalCells = this.width * this.height;
    const nonMineCells = totalCells - this.mineCount;
    if (this.cellsRevealed >= nonMineCells && !this.gameOver) {
      this.gameOver = true;
      this.victory = true;
    }
  }
}
