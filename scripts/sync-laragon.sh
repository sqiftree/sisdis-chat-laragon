#!/usr/bin/env bash
set -euo pipefail
export LC_ALL=C
cd "$(dirname "$0")/.."
destination=/mnt/c/laragon-sisdis/www/sisdis-wa-arch
state="${XDG_STATE_HOME:-$HOME/.local/state}/sisdis-chat-laragon"
[[ -d "$destination/app/public" ]] || { echo "Folder Laragon belum tersedia" >&2; exit 1; }
mkdir -p "$state"
exec 9>"$state/lock"
flock 9
work=$(mktemp -d "$state/run.XXXXXXXX")
trap 'rm -rf -- "$work"' EXIT

# Sertakan perubahan yang belum di-commit dan aset baru yang diizinkan Git.
git ls-files --cached --others --exclude-standard -z -- app backup-service database docs \
    README.md PRD_Sisdis_WhatsApp_Architecture.md SETUP_Laragon_Docker_WSL.md > "$work/candidates"
while IFS= read -r -d '' file; do
    if [[ -f "$file" && ! -L "$file" ]]; then printf '%s\0' "$file"; fi
done < "$work/candidates" | sort -zu > "$work/current"

# Hapus hanya file yang sebelumnya dikelola dan telah dihapus di sumber.
cp "$work/current" "$work/transfer"
if [[ -f "$state/files" ]]; then
    comm -z -23 "$state/files" "$work/current" > "$work/removed"
    while IFS= read -r -d '' file; do
        if [[ ! -e "$file" && ! -L "$file" ]]; then printf '%s\0' "$file"; fi
    done < "$work/removed" >> "$work/transfer"
fi
rsync -tc --from0 --files-from="$work/transfer" --delete-missing-args \
    --exclude=config.local.php --exclude='.env*' --exclude='*.pem' --exclude='storage/backups/' \
    ./ "$destination/"
mv "$work/current" "$state/files"
