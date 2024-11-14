import MarkdownIt from "markdown-it";
import php from "highlight.js/lib/languages/php";
import hljs from "highlight.js";

hljs.registerLanguage("php", php);

const md = new MarkdownIt({
  highlight: (str, lang) => {
    if (lang && hljs.getLanguage(lang)) {
      try {
        return (
          `<pre><code class="hljs language-${lang}">` +
          hljs.highlight(str, { language: lang }).value +
          "</code></pre>"
        );
      } catch (__) {}
    }
    return `<pre><code class="hljs">${md.utils.escapeHtml(str)}</code></pre>`;
  },
});

fetch("/README.md")
  .then((response) => response.text())
  .then((text) => {
    document.getElementById("markdown").innerHTML = md.render(text);
    hljs.highlightAll()
  });
