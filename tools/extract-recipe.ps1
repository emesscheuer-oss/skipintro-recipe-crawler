param(
  [Parameter(Mandatory=$true)]
  [string]$Path
)

$content = Get-Content -Raw -Path $Path

# Extract JSON-LD blocks
$regex = '<script[^>]*type=["'']application/ld\+json["''][^>]*>(.*?)</script>'
$matches = [System.Text.RegularExpressions.Regex]::Matches($content, $regex, [System.Text.RegularExpressions.RegexOptions]::Singleline)

function Flatten([object]$o){
  if ($null -eq $o) { return @() }
  if ($o -is [System.Collections.IEnumerable] -and -not ($o -is [string])) {
    $list = @()
    foreach ($item in $o) { $list += Flatten $item }
    return ,$list
  } else {
    return ,$o
  }
}

$recipes = @()
foreach ($m in $matches) {
  $json = $m.Groups[1].Value.Trim()
  # Clean common artifacts
  $json = $json -replace '<!--.*?-->', ''
  $json = $json -replace '&quot;', '"'
  try {
    $obj = ConvertFrom-Json -InputObject $json -ErrorAction Stop
  } catch {
    continue
  }
  $candidates = Flatten $obj
  foreach ($c in $candidates) {
    if ($null -eq $c) { continue }
    $hasType = $c.PSObject.Properties.Name -contains '@type'
    if ($hasType) {
      $type = $c.'@type'
      $types = @()
      if ($type -is [System.Collections.IEnumerable] -and -not ($type -is [string])) { $types = @($type) } else { $types = @($type) }
      if ($types -contains 'Recipe') { $recipes += $c; continue }
    }
    if ($c.PSObject.Properties.Name -contains 'recipeIngredient' -or $c.PSObject.Properties.Name -contains 'recipeInstructions') {
      $recipes += $c
    }
  }
}

if ($recipes.Count -eq 0) {
  Write-Output (@{ path = $Path; found = $false } | ConvertTo-Json -Depth 7)
  exit 0
}

$rec = $recipes[0]

# Helper to take first non-empty value
function TakeFirst($v) {
  if ($null -eq $v) { return $null }
  if ($v -is [System.Collections.IEnumerable] -and -not ($v -is [string])) {
    foreach ($i in $v) { if ($i) { return $i } }
    return $null
  }
  return $v
}

# Instructions
$instr = $rec.recipeInstructions
$instrCount = $null
$instrSample = @()
if ($instr) {
  if ($instr -is [System.Collections.IEnumerable] -and -not ($instr -is [string])) {
    $i = 0
    foreach ($st in $instr) {
      $i += 1
      if ($instrSample.Count -lt 3) {
        if ($st -is [string]) { $instrSample += $st }
        else { $instrSample += ($st.text ?? $st.name ?? ($st.'@type')) }
      }
    }
    $instrCount = $i
  } else {
    $instrCount = 1
    $instrSample = @("$instr")
  }
}

# Image URL normalization
$image = $rec.image
$imageUrl = $null
if ($image) {
  if ($image -is [string]) { $imageUrl = $image }
  elseif ($image -is [System.Collections.IEnumerable]) {
    foreach ($im in $image) {
      if ($im -is [string]) { $imageUrl = $im; break }
      else { $imageUrl = $im.url ?? $im['@id']; if ($imageUrl) { break } }
    }
  } else { $imageUrl = $image.url ?? $image['@id'] }
}

$ingredientCount = $null
$sampleIngredients = @()
if ($rec.PSObject.Properties.Name -contains 'recipeIngredient') {
  $ingredientCount = ($rec.recipeIngredient | Measure-Object).Count
  $sampleIngredients = @($rec.recipeIngredient | Select-Object -First 5)
}

$result = [pscustomobject]@{
  path = $Path
  found = $true
  name = $rec.name
  description = $rec.description
  recipeYield = $rec.recipeYield
  prepTime = $rec.prepTime
  cookTime = $rec.cookTime
  totalTime = $rec.totalTime
  recipeIngredientCount = $ingredientCount
  sampleIngredients = $sampleIngredients
  instructionCount = $instrCount
  sampleInstructions = $instrSample
  image = $imageUrl
}

$result | ConvertTo-Json -Depth 7

