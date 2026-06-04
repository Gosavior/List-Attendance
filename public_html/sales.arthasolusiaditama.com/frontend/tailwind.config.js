 
export default {
  content: [
    "./index.html",
    "./src/**/*.{js,ts,jsx,tsx}",
  ],
  safelist: [
    
    { pattern: /bg-(purple|lime|amber|teal|sky|green|red|indigo|stone)-(50|100|200|300|400|500|600|700)/ },
    { pattern: /text-(purple|lime|amber|teal|sky|green|red|indigo|stone)-(50|100|200|300|400|500|600|700)/ },
    { pattern: /border-(purple|lime|amber|teal|sky|green|red|indigo|stone)-(50|100|200|300|400|500|600|700)/ },
    { pattern: /hover:bg-(purple|lime|amber|teal|sky|green|red|stone)-(400|500|600)/ },
  ],
  theme: {
    extend: {},
  },
  plugins: [],
}
