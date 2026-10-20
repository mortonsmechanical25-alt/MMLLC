import {build} from 'esbuild';
import path from 'node:path';
import {fileURLToPath} from 'node:url';
const base=path.dirname(fileURLToPath(import.meta.url));
for(const [entry,out] of [['main.tsx','app.js'],['print.tsx','print.js']])await build({absWorkingDir:base,entryPoints:[entry],bundle:true,outfile:path.resolve(base,'../../public_html/assets',out),minify:true,jsx:'automatic',format:'iife',platform:'browser',target:['es2020'],define:{'process.env.NODE_ENV':'"production"'}});
