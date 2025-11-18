local cfg = {
    ['channel'] = code,
    ['excludeDefaultExtraInfo'] = true,
    ['extraInfo'] = {
        ['cx'] = cx
    }
}
local channelInfoList = {}
table.insert(channelInfoList, cfg)
