#!/usr/bin/env python3
"""Create file-only Service Desk update ZIPs with explicit integrity hashes."""
import argparse, hashlib, json, re, zipfile
from pathlib import Path

def allowed(name):
    if len(name)>200 or not re.fullmatch(r'(public|private)/[A-Za-z0-9_-][A-Za-z0-9_./-]*',name):
        return False
    if any(not p or p.startswith('.') for p in name.split('/')):
        return False
    area,rel=name.split('/',1)
    if area=='private':
        return rel in {'google-lib.php','workflows.php'}
    return (rel.lower() not in {'updates.php','setup.php','webhook.php'}
            and not rel.lower().startswith('uploads/')
            and re.search(r'\.(php|js|css|svg|png|jpg|jpeg|webp|ico|html|json|md)$',rel,re.I))

def main():
    p=argparse.ArgumentParser(description=__doc__)
    p.add_argument('source',type=Path)
    p.add_argument('output',type=Path)
    p.add_argument('--version',required=True)
    p.add_argument('--title',required=True)
    args=p.parse_args()
    if not args.source.is_dir() or not args.version or len(args.version)>100 or not args.title or len(args.title)>200:
        p.error('Provide a payload directory, title, and version within manifest limits.')
    files=[];payload=[];seen=set();total=0
    for f in sorted(args.source.rglob('*')):
        if f.is_symlink():p.error('Symbolic links are not allowed.')
        if f.is_dir():continue
        name=f.relative_to(args.source).as_posix()
        if not allowed(name) or name.lower() in seen:p.error('Unsafe, duplicate, or protected payload path: '+name)
        seen.add(name.lower());raw=f.read_bytes();total+=len(raw)
        if len(raw)>20971520 or total>52428800:p.error('Payload exceeds update size limits.')
        files.append({'path':name,'sha256':hashlib.sha256(raw).hexdigest()});payload.append((name,raw))
    if not files or len(files)>500:p.error('Provide 1–500 payload files.')
    manifest={'format':'mortons-desk-update-v1','version':args.version,'title':args.title,'files':files}
    raw=json.dumps(manifest,indent=2).encode()
    if len(raw)>65536:p.error('Manifest exceeds 64 KB.')
    args.output.parent.mkdir(parents=True,exist_ok=True)
    with zipfile.ZipFile(args.output,'w',zipfile.ZIP_DEFLATED) as z:
        z.writestr('update.json',raw)
        for name,content in payload:z.writestr(name,content)
    if args.output.stat().st_size>20971520:
        args.output.unlink();p.error('Compressed ZIP exceeds 20 MB.')
    print(args.output)

if __name__=='__main__':main()
