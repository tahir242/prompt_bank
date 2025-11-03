/**
 * Simple Diff Library
 * Generates HTML diff comparison between two text strings
 */

function generateDiff(oldText, newText) {
    const oldLines = oldText.split('\n');
    const newLines = newText.split('\n');
    
    const diff = computeDiff(oldLines, newLines);
    return renderDiff(diff);
}

function computeDiff(oldLines, newLines) {
    const n = oldLines.length;
    const m = newLines.length;
    const max = n + m;
    
    // Using Myers diff algorithm (simplified)
    const v = {};
    const trace = [];
    
    v[1] = 0;
    
    for (let d = 0; d <= max; d++) {
        trace.push({...v});
        
        for (let k = -d; k <= d; k += 2) {
            let x;
            
            if (k === -d || (k !== d && v[k - 1] < v[k + 1])) {
                x = v[k + 1];
            } else {
                x = v[k - 1] + 1;
            }
            
            let y = x - k;
            
            while (x < n && y < m && oldLines[x] === newLines[y]) {
                x++;
                y++;
            }
            
            v[k] = x;
            
            if (x >= n && y >= m) {
                return backtrack(trace, oldLines, newLines, d);
            }
        }
    }
    
    return [];
}

function backtrack(trace, oldLines, newLines, d) {
    const diff = [];
    let x = oldLines.length;
    let y = newLines.length;
    
    for (let i = d; i >= 0; i--) {
        const v = trace[i];
        const k = x - y;
        
        let prevK;
        if (k === -i || (k !== i && v[k - 1] < v[k + 1])) {
            prevK = k + 1;
        } else {
            prevK = k - 1;
        }
        
        const prevX = v[prevK];
        const prevY = prevX - prevK;
        
        while (x > prevX && y > prevY) {
            diff.unshift({ type: 'equal', oldLine: x - 1, newLine: y - 1, content: oldLines[x - 1] });
            x--;
            y--;
        }
        
        if (i > 0) {
            if (x === prevX) {
                diff.unshift({ type: 'add', newLine: y - 1, content: newLines[y - 1] });
                y--;
            } else {
                diff.unshift({ type: 'delete', oldLine: x - 1, content: oldLines[x - 1] });
                x--;
            }
        }
    }
    
    return diff;
}

function renderDiff(diff) {
    if (diff.length === 0) {
        return '<p class="text-gray-500">No changes detected</p>';
    }
    
    let html = '<div class="diff-view">';
    html += '<div class="grid grid-cols-2 gap-4">';
    
    // Old version (left)
    html += '<div class="border border-gray-300 rounded">';
    html += '<div class="bg-red-100 px-3 py-2 font-semibold text-sm border-b border-gray-300">Old Version</div>';
    html += '<div class="p-3 font-mono text-xs overflow-x-auto">';
    
    diff.forEach(item => {
        if (item.type === 'delete') {
            html += `<div class="bg-red-50 text-red-800 px-2 py-1 rounded mb-1">- ${escapeHtml(item.content)}</div>`;
        } else if (item.type === 'equal') {
            html += `<div class="text-gray-700 px-2 py-1">${escapeHtml(item.content)}</div>`;
        }
    });
    
    html += '</div></div>';
    
    // New version (right)
    html += '<div class="border border-gray-300 rounded">';
    html += '<div class="bg-green-100 px-3 py-2 font-semibold text-sm border-b border-gray-300">New Version</div>';
    html += '<div class="p-3 font-mono text-xs overflow-x-auto">';
    
    diff.forEach(item => {
        if (item.type === 'add') {
            html += `<div class="bg-green-50 text-green-800 px-2 py-1 rounded mb-1">+ ${escapeHtml(item.content)}</div>`;
        } else if (item.type === 'equal') {
            html += `<div class="text-gray-700 px-2 py-1">${escapeHtml(item.content)}</div>`;
        }
    });
    
    html += '</div></div>';
    html += '</div></div>';
    
    return html;
}

// Simple inline diff for single line
function generateInlineDiff(oldText, newText) {
    if (oldText === newText) {
        return `<span class="text-gray-700">${escapeHtml(oldText)}</span>`;
    }
    
    const oldWords = oldText.split(/(\s+)/);
    const newWords = newText.split(/(\s+)/);
    
    let html = '<div class="inline-diff">';
    
    // Simple word-by-word comparison
    const maxLen = Math.max(oldWords.length, newWords.length);
    
    for (let i = 0; i < maxLen; i++) {
        const oldWord = oldWords[i] || '';
        const newWord = newWords[i] || '';
        
        if (oldWord === newWord) {
            html += `<span class="text-gray-700">${escapeHtml(oldWord)}</span>`;
        } else {
            if (oldWord) {
                html += `<span class="bg-red-100 text-red-800 line-through">${escapeHtml(oldWord)}</span>`;
            }
            if (newWord) {
                html += `<span class="bg-green-100 text-green-800">${escapeHtml(newWord)}</span>`;
            }
        }
    }
    
    html += '</div>';
    return html;
}
